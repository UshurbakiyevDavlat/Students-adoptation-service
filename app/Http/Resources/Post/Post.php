<?php

namespace App\Http\Resources\Post;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;

class Post extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request): array|\JsonSerializable|Arrayable
    {
        $post = parent::toArray($request);
        $post['amountOfLikes'] = parent::likes()->where('liked', 1)->count();
        $post['amountOfComments'] = parent::comments()->where('status', 1)->count();
        $post['authorName'] = parent::user()->name;

        return $post;
    }
}
