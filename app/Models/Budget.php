<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $amount
 * @property-read Category|null $category
 */
class Budget extends Model
{
    use BelongsToUser;

    protected $fillable = ['category_id', 'month', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
