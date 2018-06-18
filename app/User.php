<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\DatesTranslator;
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    use Notifiable, HasRoles, DatesTranslator, SoftDeletes;


    protected $fillable = [
        'name','lastname', 'email', 'password', 'profilephoto',
    ];
    protected $dates = ['created_at', 'updated_at', 'disabled_at','mydate','deleted_at'];


    public static function boot()
    {
        parent::boot();
        static::deleting(function ($user) {


            $mimessages = Message::where('from','=',$user->id)->get();
            $mimessages1 = Message::where('to','=',$user->id)->get();
            $mimessages->each->delete();
            $mimessages1->each->delete();

            $user->categories->each->update([
                'user_id' => '2'
            ]);

            $conservatepremium = Document::where('user_id','=',$user->id)->where('premium','=','1')->get();
            $conservatepremium->each->update([
                'user_id' => '2'
            ]);

            $deletepublic = Document::where('user_id','=',$user->id)->where('premium','=','0')->get();
            $deletepublic->each->delete();

        });
    }


    protected $hidden = [
        'password', 'remember_token',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function payments()
    {
        return $this->hasMany(Pay::class);
    }

    public function newFromBuilder($attributes = [], $connection = null)
    {
        return parent::newFromBuilder($attributes, $connection); // TODO: Change the autogenerated stub
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function like()
    {
        return $this->hasMany(Like::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class,'from');
    }


}
