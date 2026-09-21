<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts;

interface SoftDeletableInterface {
    public function softDelete(): void;
    public function restore(): void;
    public function isSoftDeleted(): bool;
}
