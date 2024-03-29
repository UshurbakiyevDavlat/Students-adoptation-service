<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\User\AvatarUploadRequest;
use App\Http\Requests\User\UserCreateRequest;
use App\Http\Resources\User\User as UserResource;
use App\Http\Resources\User\UserCollection;
use App\Models\User;
use App\Models\UserEntryCode;
use App\Notifications\ResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/user/",
     *     summary="GET list of users",
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     )
     * )
     */

    public function index(): UserCollection
    {
        return UserCollection::make((new User())->setFilters(['name', 'phone'])->getFiltered());
    }

    /**
     * @OA\Get(
     *     path="/api/user/{id}",
     *     summary="GET concrete user",
     *     @OA\Parameter(
     *         description="Parameter with mutliple examples",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         @OA\Examples(example="int", value="1", summary="An int value."),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     )
     * )
     */

    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    /**
     * @OA\Post(
     *     path="/api/user",
     *     summary="Create user",
     *     @OA\Parameter(
     *         description="Parameter phone",
     *         in="path",
     *         name="phone",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         @OA\Examples(example="string", value="+77477782877", summary="A string with phone."),
     *     ),
     *     @OA\Parameter(
     *         description="Parameter password",
     *         in="path",
     *         name="password",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         @OA\Examples(example="string", value="somePassword", summary="A string with password."),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     )
     * )
     */
    public function create(UserCreateRequest $request): JsonResponse|UserResource
    {
        $userData = $request->validated();
        $userData['password'] = Hash::make($userData['password']);
        $userData['uuid'] = Str::uuid();
        $userData['avatar'] = 'users/avatar/default.png';

        $entryCode = UserEntryCode::where('code', $userData['code'])
            ->where('used', 0)
            ->first();

        $errors = null;

        if (!$entryCode) {
            return response()
                ->json(['status' => 403, 'message' => 'This code does not exist or has been used.'], 403);
        }

        $user = User::create($userData);
        $entryCode->used = 1;
        $entryCode->save();
        DB::commit();

        $credentials = $request->only(['phone', 'password']);
        $token = auth()->attempt($credentials);
        $user->token = (object)['access_token' => $token];

        return $errors ?: UserResource::make($user)->response()->setStatusCode(200);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $token = Str::random(64);
        $data = $request->only('email', 'password', 'password_confirmation');

        //TODO Вот такие конструкции нужно выносить в отдельный сервис или метод,
        // лучше сервис, позже надо найти вынести и заменить.
        $entryCode = UserEntryCode::where('code', $request->code)->first();
        $user = User::where('email', $data['email'])->first();

        if ($entryCode && ($user->id !== $entryCode->user->id)) {
            return response()->json(['status' => 403, 'message' => 'This user has not such code'], 403);
        }

        $data['token'] = $token;

        DB::table('password_resets')->insert([
            'email' => $data['email'],
            'token' => bcrypt($data['token']),
        ]);

        $status = Password::reset(
            $data,
            static function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
                $user->notify((new ResetPassword($password)));

                event(new PasswordReset($user));
            }
        );

        $code = $status === Password::PASSWORD_RESET ? 200 : 100;

        return response()->json(['text' => __($status), 'status' => $code]);
    }

    public function delete(User $user): ?bool
    {
        return $user->delete();
    }

    public function saveDeviceToken(Request $request): JsonResponse
    {
        $deviceId = $request->deviceId ?? null;

        if (!$deviceId) {
            return response()->json(['status' => 400, 'message' => 'Device id is required']);
        }

        $user = auth()->user();
        $user->fcm_token = $deviceId;
        $user->save();

        return response()->json(['status' => 200, 'message' => 'Device id saved']);
    }

    public function uploadAvatar(AvatarUploadRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['avatar'] = Storage::disk('public')->put('/users/avatar', $validated['image']);

        $user = auth()->user();
        $user->avatar = $validated['avatar'];
        $user->save();

        return response()->json(['status' => 200, 'message' => 'Avatar uploaded']);
    }
}
