<?php
namespace CarloNicora\Minimalism\Services\Builder\Objects;

use CarloNicora\Minimalism\Interfaces\Data\Interfaces\DataFunctionInterface;

class RelationshipBuilder
{
    /**
     * RelationshipBuilder constructor.
     * @param string $name
     * @param string $builderClassName
     * @param DataFunctionInterface $function
     * @param bool $dontLoadChildren
     * @param bool $isOptional
     * @param bool $isList
     */
    public function __construct(
        private string $name,
        private string $builderClassName,
        private DataFunctionInterface $function,
        private bool $dontLoadChildren=false,
        private bool $isOptional = false,
        private bool $isList = false
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
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    /**
     * @return bool
     */
    public function isList(): bool
    {
        return $this->isList;
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