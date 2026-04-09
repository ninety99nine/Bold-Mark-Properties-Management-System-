<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'title'  => 'string',
        'status' => PostStatus::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'body', 'status', 'author_id',
    ];

    /**
     * Scope a query by search term.
     *
     * @param Builder $query
     * @param string $searchTerm
     * @return void
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->where('title', 'like', '%' . $searchTerm . '%');
    }

    /**
     * Get featured image.
     *
     * @return MorphOne
     */
    public function featuredImage(): MorphOne
    {
        return $this->morphOne(MediaFile::class, 'mediable')->where('type', 'featured');
    }

    /**
     * Get media files.
     *
     * @return MorphMany
     */
    public function mediaFiles(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'mediable');
    }

    /**
     * Get comments.
     *
     * @return HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get published comments.
     *
     * @return HasMany
     */
    public function publishedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('status', 'published');
    }

    /**
     * Get revisions.
     *
     * @return HasMany
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class);
    }

    /**
     * Get tags.
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag')
                    ->withPivot(['id', 'post_id', 'tag_id', 'created_at'])
                    ->using(PostTag::class)
                    ->as('post_tag');
    }

    /**
     * Get categories.
     *
     * @return BelongsToMany
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'post_category')
                    ->withPivot(['id', 'post_id', 'category_id'])
                    ->as('post_category');
    }
}
