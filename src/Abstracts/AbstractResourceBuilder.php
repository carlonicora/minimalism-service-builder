<?php
namespace CarloNicora\Minimalism\Services\Builder\Abstracts;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Interfaces\Encrypter\Interfaces\EncrypterInterface;
use CarloNicora\Minimalism\Services\Builder\Interfaces\ResourceBuilderInterface;
use CarloNicora\Minimalism\Interfaces\ServiceInterface;
use CarloNicora\Minimalism\Services\Path;
use Exception;

abstract class AbstractResourceBuilder implements ResourceBuilderInterface
{
    /** @var ResourceObject  */
    protected ResourceObject $response;

    /** @var string  */
    protected string $type;

    /**
     * UserBuilder constructor.
     * @param Path $path
     * @param EncrypterInterface $encrypter
     * @param ServiceInterface|null $transformer
     * @throws Exception
     */
    public function __construct(
        protected Path $path,
        protected EncrypterInterface $encrypter,
        protected ?ServiceInterface $transformer=null,
    )
    {
        $this->response = new ResourceObject($this->type);
    }

    /**
     * @param array $data
     */
    abstract public function setAttributes(
        array $data
    ): void;

    /**
     * @param array $data
     */
    public function setLinks(
        array $data
    ): void
    {
    }

    /**
     * @param array $data
     */
    public function setMeta(
        array $data
    ): void
    {
    }

    /**
     * @return array|null
     */
    public function getRelationshipReaders(): ?array
    {
        return null;
    }

    /**
     * @return ResourceObject
     */
    final public function getResourceObject(): ResourceObject
    {
        return $this->response;
    }
}