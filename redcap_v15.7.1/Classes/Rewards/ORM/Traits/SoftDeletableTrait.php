<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Traits;

use DateTime;
use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Attributes\SoftDeleteField;

trait SoftDeletableTrait
{
    public function softDelete(): void {
        $field = $this->getSoftDeleteFieldName();
        $reflection = new ReflectionProperty($this, $field);
        $reflection->setAccessible(true);

        $currentValue = $reflection->getValue($this);

        if ($currentValue instanceof DateTimeInterface || $currentValue === null) {
            $reflection->setValue($this, new DateTime());
        } elseif (is_bool($currentValue)) {
            $reflection->setValue($this, true);
        }
    }

    public function restore(): void {
        $field = $this->getSoftDeleteFieldName();
        $reflection = new ReflectionProperty($this, $field);
        $reflection->setAccessible(true);

        $currentValue = $reflection->getValue($this);

        if ($currentValue instanceof DateTimeInterface || $currentValue === null) {
            $reflection->setValue($this, null);
        } elseif (is_bool($currentValue)) {
            $reflection->setValue($this, false);
        }
    }

    public function isSoftDeleted(): bool {
        $field = $this->getSoftDeleteFieldName();
        $reflection = new ReflectionProperty($this, $field);
        $reflection->setAccessible(true);

        $value = $reflection->getValue($this);

        if ($value instanceof DateTimeInterface) {
            return $value !== null;
        } elseif (is_bool($value)) {
            return $value === true;
        }

        return false;
    }

    private function getSoftDeleteFieldName(): string {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(SoftDeleteField::class);
            if (!empty($attributes)) {
                return $property->getName();
            }
        }

        throw new \LogicException("No #[SoftDeleteField] attribute found in " . static::class);
    }
}

