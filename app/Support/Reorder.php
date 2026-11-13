<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Reorder
{
    /**
     * Move um item uma posição para cima (-1) ou para baixo (+1) e renumera sort_order.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $ordered  consulta já ordenada (escopo do usuário aplicado)
     */
    public static function move(Builder $ordered, int $id, int $direction): void
    {
        $ids = $ordered->pluck('id')->all();
        $index = array_search($id, $ids, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $model = $ordered->getModel();
        foreach ($ids as $position => $itemId) {
            $model->newQuery()->whereKey($itemId)->update(['sort_order' => $position]);
        }
    }
}
