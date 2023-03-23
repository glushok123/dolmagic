<?php

namespace App\Repository\Base;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModelRepository
{
    protected $entity;
    protected static $entityClass = Model::class;

    public function __construct(Model $entity)
    { 
        $this->setEntity($entity);
    }

    /**
     * Установка сущности
     *
     * @param Model $entity
     * @return void
     */
    public function setEntity(Model $entity): void
    {
        if (! $entity instanceof static::$entityClass) {
            throw new \RuntimeException('Класс сущности должен быть унаследован от ' . static::$entityClass);
        }
        
        $this->entity = $entity;
    }

    /**
     * @return Model
     */
    public function getEntity(): Model
    {
        return $this->entity;
    }

    /**
     * @return null|Model
     */
    public static function findOne(int $id): ?Model
    {
        return static::$entityClass::find($id);
    }

    /**
     * @return null|Model
     */
    public static function findOneWithTrashed(int $id): ?Model
    {
        return static::$entityClass::withTrashed()->find($id);
    }

    /**
     * Проверка есть ли связь
     *
     * @param string $relation
     * @return boolean
     */
    public function has(string $relation): bool
    {
        return $this->entity->{$relation}()->exists();
    }

    /**
     * Проверка нет ли связи
     *
     * @param string $relation
     * @return boolean
     */
    public function hasNo(string $relation): bool
    {
        return ! $this->has($relation);
    }
    
    /**
     * @return mixed
     */
    public function getAttribute(string $attribute)
    {
        return $this->entity->{$attribute};
    }

    /**
     * @param  array $attributes
     * @return void
     */
    public function fill(array $attributes = []): void
    {
        $this->entity->fill($attributes);
    }

    /**
     * @return void
     */
    public function save(): void
    {
        $this->entity->save();
    }

    /**
     * @param  null|Model $model
     * @return BaseModelRepository
     */
    public static function getInstance(?Model $model = null): self
    {
        $entity = $model ?: new static::$entityClass();
     
        return new static($entity);
    }
    
}
