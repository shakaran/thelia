<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\DocumentsExportInterface;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\ImportExport\Export\ImagesExportInterface;
use Thelia\Model\Base\Export as BaseExport;
use Thelia\Model\Map\ExportTableMap;

class Export extends BaseExport
{
    protected static $cache;

    public function upPosition()
    {

        if (($position = $this->getPosition()) > 1) {

            $previous = ExportQuery::create()
                ->filterByPosition($position - 1)
                ->findOneByExportCategoryId($this->getExportCategoryId());

            if (null !== $previous) {
                $previous->setPosition($position)->save();
            }

            $this->setPosition($position - 1)->save();
        }

        return $this;
    }

    public function downPosition()
    {
        $max = ExportQuery::create()
            ->orderByPosition(Criteria::DESC)
            ->select(ExportTableMap::POSITION)
            ->findOne()
        ;

        $count = $this->getExportCategory()->countExports();

        if ($count > $max) {
            $max = $count;
        }

        $position = $this->getPosition();

        if ($position < $max) {

            $next = ExportQuery::create()
                ->filterByPosition($position + 1)
                ->findOneByExportCategoryId($this->getExportCategoryId());

            if (null !== $next) {
                $next->setPosition($position)->save();
            }

            $this->setPosition($position + 1)->save();
        }

        return $this;
    }

    public function updatePosition($position)
    {
        $reverse = ExportQuery::create()
            ->findOneByPosition($position)
        ;

        if (null !== $reverse) {
            $reverse->setPosition($this->getPosition())->save();
        }

        $this->setPosition($position)->save();
    }

    public function setPositionToLast()
    {
        $max = ExportQuery::create()
            ->orderByPosition(Criteria::DESC)
            ->select(ExportTableMap::POSITION)
            ->findOne()
        ;

        if (null === $max) {
            $this->setPosition(1);
        } else {
            $this->setPosition($max+1);
        }

        return $this;
    }

    /**
     * @param  ContainerInterface                        $container
     * @return \Thelia\ImportExport\Export\ExportHandler
     * @throws \ErrorException
     */
    public function getHandleClassInstance(ContainerInterface $container)
    {
        $class = $this->getHandleClass();

        if ($class[0] !== "\\") {
            $class = "\\" . $class;
        }

        if (!class_exists($class)) {
            $this->delete();

            throw new \ErrorException(
                Translator::getInstance()->trans(
                    "The class \"%class\" doesn't exist",
                    [
                        "%class" => $class
                    ]
                )
            );
        }

        $instance = new $class($container);

        if (!$instance instanceof ExportHandler) {
            $this->delete();

            throw new \ErrorException(
                Translator::getInstance()->trans(
                    "The class \"%class\" must extend %baseClass",
                    [
                        "%class" => $class,
                        "%baseClass" => "Thelia\\ImportExport\\Export\\ExportHandler",
                    ]
                )
            );
        }

        return static::$cache = $instance;
    }

    /**
     * @param ConnectionInterface $con
     *
     * Handle the position of other exports
     */
    public function delete(ConnectionInterface $con = null)
    {
        $imports = ExportQuery::create()
            ->filterByPosition($this->getPosition(), Criteria::GREATER_THAN)
            ->find()
        ;

        foreach ($imports as $import) {
            $import->setPosition($import->getPosition() - 1);
        }

        $imports->save();

        parent::delete($con);
    }

    public function hasImages(ContainerInterface $container)
    {
        if (static::$cache === null) {
            $this->getHandleClassInstance($container);
        }

        return static::$cache instanceof ImagesExportInterface;
    }

    public function hasDocuments(ContainerInterface $container)
    {
        if (static::$cache === null) {
            $this->getHandleClassInstance($container);
        }

        return static::$cache instanceof DocumentsExportInterface;
    }
}
