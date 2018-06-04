<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\DatesTranslator;
class Document extends Model
{
    use DatesTranslator;
    protected $fillable = ['name', 'description', 'url', 'category_id', 'user_id', 'extension_id', 'storage', 'premium' ];
    //protected $dates = ['created_at', 'updated_at', 'disabled_at','mydate'];
    protected $hidden = ['storage'];


    public static function boot()
    {
        parent::boot();
        static::deleting(function ($documento) {

            $documento->likes->each->delete();
            Storage::disk('public')->delete($documento->storage);
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Pay::class);
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class,'document_tags');
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function syncTags($tags)
    {
        $tagIds = collect($tags)->map(function ($tag){
            return Tag::find($tag) ? $tag : Tag::create(['name' => $tag])->id;
        });
        return $this->tags()->sync($tagIds);
    }

}
