<?php

namespace App\Services\Base;

use App\Repository\Base\BaseModelRepository;

abstract class BaseModelService
{
    /**
     * @var BaseModelRepository
     */
    protected $repository;

    /**
     * @var string
     */
    protected static $repositoryClass = BaseModelRepository::class;

    public function __construct($repository)
    {
        $this->setRepository($repository);
    }

    /**
     * Установка репозитория.
     *
     * @param  BaseModelRepository $repository
     * @return void
     */
    public function setRepository(BaseModelRepository $repository): void
    {
        if (! $repository instanceof static::$repositoryClass) {
            throw new \RuntimeException('Класс репозитория должен быть унаследован от ' . static::$repositoryClass);
        }
        
        $this->repository = $repository;
    }

    /**
     * @return BaseModelRepository
     */
    public function getRepository(): BaseModelRepository
    {
        return $this->repository;
    }

    /**
     * @return string
     */
    protected function getRelationMethod(string $relation): string
    {
        return 'has' . ucfirst($relation);
    }

    /**
     * @return bool
     */
    protected function hasRelationMethod(string $relation): bool
    {
        return method_exists(
            $this->repository,
            $this->getRelationMethod($relation)
        );
    }

    /**
     * @return mixed
     */
    protected function getRelationAttribute(string $relation, string $property, $default = null)
    {
        $method = $this->getRelationMethod($relation);

        $check = $this->hasRelationMethod($relation) ? $this->repository->{$method}() : $this->repository->has($relation);

        $entity = $this->repository->getEntity();

        return  $check ? $entity->$relation->{$property} : $default;
    }
    
    /**
     * Выводит значение аттрибута.
     *
     * @param  string $relation
     * @param  string $property
     * @param  string $default
     * @param  $callback
     * @return string
     */
    protected function printRelationAttribute(
        string $relation,
        string $property,
        string $default = 'Не задано',
        $callback = null
    ): string 
    {
        $value = $this->getRelationAttribute($relation, $property, false);
        
        if ($value === false) {
            return (string) $default;
        }

        if (is_callable($callback)) {
            return (string) $callback($value);
        }

        return (string) $value;
    }

    /**
     * Возвращает аттрибут сущности.
     *
     * @param  string $attribute
     * @param  string $default
     * @param  \Closure $callback
     * @return string
     */
    public function printAttribute(
        string $attribute,
        string $default = 'Не задано',
        $callback = null
    ): string 
    {
        $value = $this->repository->getAttribute($attribute);

        if ($value === null) {
            return (string) $default;
        }

        if (is_callable($callback)) {
            return (string) $callback($value);
        }
        
        return (string) $value;
    }

    /**
     * @return BaseModelService
     */
    public static function getInstance(): self
    {
        $repositoryInstance = static::$repositoryClass::getInstance();

        return new static($repositoryInstance);
    }

}
