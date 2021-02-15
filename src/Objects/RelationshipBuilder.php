<?php
namespace CarloNicora\Minimalism\Services\Builder\Objects;

use CarloNicora\Minimalism\Interfaces\DataFunctionInterface;

class RelationshipBuilder
{
    /**
     * RelationshipBuilder constructor.
     * @param string $name
     * @param string $builderClassName
     * @param DataFunctionInterface $function
     * @param bool $dontLoadChildren
     */
    public function __construct(
        private string $name,
        private string $builderClassName,
        private DataFunctionInterface $function,
        private bool $dontLoadChildren=false,
    )
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isDontLoadChildren(): bool
    {
        return $this->dontLoadChildren;
    }

    /**
     * @return DataFunctionInterface
     */
    public function getDataFunction(): DataFunctionInterface
    {
        return $this->function;
    }

    /**
     * @return string
     */
    public function getBuilderClassName(): string
    {
        return $this->builderClassName;
    }
}