<?php

namespace App\Enums;

/**
 * 変更の記録で、何をしたか（R-08）
 */
enum OrderLogAction: string
{
    case Created = 'created';
    case Status = 'status';
    case Product = 'product';
    case Quantity = 'quantity';
    case MaterialReadyDate = 'material_ready_date';
    case DueDate = 'due_date';
    case AssigneeAdded = 'assignee_added';
    case AssigneeRemoved = 'assignee_removed';

    public function label(): string
    {
        return match ($this) {
            self::Created => '指示を登録した',
            self::Status => '状態',
            self::Product => '製品',
            self::Quantity => '数量',
            self::MaterialReadyDate => '材料集結日',
            self::DueDate => '納期',
            self::AssigneeAdded => '担当を足した',
            self::AssigneeRemoved => '担当を外した',
        };
    }
}
