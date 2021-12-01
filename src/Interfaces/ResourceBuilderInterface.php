<?php
namespace CarloNicora\Minimalism\Services\Builder\Interfaces;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Factories\ObjectFactory;
use CarloNicora\Minimalism\Interfaces\Encrypter\Interfaces\EncrypterInterface;
use CarloNicora\Minimalism\Interfaces\ServiceInterface;
use CarloNicora\Minimalism\Services\Path;

interface ResourceBuilderInterface
{
    /**
     * ResourceBuilderInterface constructor.
     * @param ObjectFactory $objectFactory
     * @param Path $path
     * @param EncrypterInterface $encrypter
     * @param ServiceInterface|null $transformer
     */
    public function __construct(
        ObjectFactory $objectFactory,
        Path $path,
        EncrypterInterface $encrypter,
        ?ServiceInterface $transformer=null,
    );

    /**
     * @param array $data
     */
    public function setAttributes(
        array $data
    ): void;

    /**
     * @param array $data
     */
    public function setLinks(
        array $data
    ): void;

    /**
     * @param array $data
     */
    public function setMeta(
        array $data
    ): void;

    /**
     * @return array|null
     */
    public function getRelationshipReaders(

    ): ?array;

    /**
     * @return ResourceObject
     */
    public function getResourceObject(
    ): ResourceObject;
}