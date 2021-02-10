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
     */
    public function __construct(
        private string $name,
        private string $builderClassName,
        private DataFunctionInterface $function,
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