<?php

namespace FalconCms\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $table = 'shop_wishlists';

    protected $fillable = ['user_id', 'product_id'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
