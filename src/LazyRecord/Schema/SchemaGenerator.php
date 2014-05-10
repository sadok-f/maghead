<?php
namespace LazyRecord\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;
use ReflectionObject;
use RecursiveRegexIterator;
use RegexIterator;
use LazyRecord\ConfigLoader;
use ClassTemplate\ClassTemplate;
use ClassTemplate\ClassConst;
use ClassTemplate\ClassInjection;
use LazyRecord\Schema;

use LazyRecord\Schema\Factory\BaseModelClassFactory;
use LazyRecord\Schema\Factory\BaseCollectionClassFactory;
use LazyRecord\Schema\Factory\CollectionClassFactory;
use LazyRecord\Schema\Factory\ModelClassFactory;
use LazyRecord\Schema\Factory\SchemaProxyClassFactory;


/**
 * Builder for building static schema class file
 */
class SchemaGenerator
{
    public $config;

    public $forceUpdate = false;

    public function __construct() 
    {
        $this->config = ConfigLoader::getInstance();
    }


    public function setForceUpdate($force) 
    {
        $this->forceUpdate = $force;
    }

    public function getBaseModelClass() 
    {
        if ( $this->config && $this->config->loaded ) {
            return $this->config->getBaseModelClass();
        }
        return 'LazyRecord\BaseModel';
    }

    public function getBaseCollectionClass() {
        if ( $this->config && $this->config->loaded ) {
            return $this->config->getBaseCollectionClass();
        }
        return 'LazyRecord\BaseCollection';
    }


    /**
     * Returns code template directory
     */
    protected function getTemplateDirs()
    {
        static $templateDir;
        if ( $templateDir ) {
            return $templateDir;
        }
        $refl = new ReflectionObject($this);
        $path = $refl->getFilename();
        return $templateDir = dirname($refl->getFilename()) . DIRECTORY_SEPARATOR . 'Templates'; // should be LazyRecord/Schema/Templates
    }

    public function preventFileDir($path,$mode = 0755)
    {
        $dir = dirname($path);
        if ( ! file_exists($dir) ) {
            mkdir( $dir , $mode, true );
        }
    }

    public function buildClassFilePath($directory, $className) 
    {
        return $directory . DIRECTORY_SEPARATOR . $className . '.php';
    }

    public function generateSchemaProxyClass($schema)
    {
        $cTemplate = SchemaProxyClassFactory::create($schema);

        $classFilePath = $this->buildClassFilePath( $schema->getDirectory(), $cTemplate->getShortClassName() );

        // always update the proxy schema file
        if ( $schema->isNewerThanFile($classFilePath) || $this->forceUpdate ) {
            if ( $this->writeClassTemplateToPath($cTemplate, $classFilePath, true) ) {
                return array( $cTemplate->getClassName() => $classFilePath );
            }
        }
    }


    public function generateBaseModelClass($schema)
    {
        $cTemplate = BaseModelClassFactory::create($schema, $this->getBaseModelClass() );

        $classFilePath = $this->buildClassFilePath( $schema->getDirectory(), $cTemplate->getShortClassName() );
        if ( $schema->isNewerThanFile($classFilePath) || $this->forceUpdate ) {
            if ( $this->writeClassTemplateToPath($cTemplate, $classFilePath, true) ) {
                return array( $cTemplate->getClassName() => $classFilePath );
            }
        }
    }



    /**
     * Generate modal class file, overwrite by default.
     *
     * @param Schema $schema
     * @param bool $force = true
     */
    public function generateModelClass($schema, $force = false)
    {
        $cTemplate = ModelClassFactory::create($schema);

        $classFilePath = $this->buildClassFilePath($schema->getDirectory(), $cTemplate->getShortClassName());
        if ( ! file_exists($classFilePath) || $schema->isNewerThanFile($classFilePath) || $force ) {
            if ( $this->writeClassTemplateToPath($cTemplate, $classFilePath, false) ) {
                return array( $cTemplate->getClassName() => $classFilePath );
            }
        }
    }

    public function generateBaseCollectionClass($schema)
    {
        $cTemplate = BaseCollectionClassFactory::create($schema, $this->getBaseCollectionClass() );
        $classFilePath = $this->buildClassFilePath($schema->getDirectory(), $cTemplate->getShortClassName());
        if ( $schema->isNewerThanFile($classFilePath) || $this->forceUpdate ) {
            if ( $this->writeClassTemplateToPath($cTemplate, $classFilePath, true) ) {
                return array( $cTemplate->getClassName() => $classFilePath );
            }
        }
    }


    /**
     * Generate collection class from a schema object.
     *
     * @param SchemaDeclare $schema
     * @return array class name, class file path
     */
    public function generateCollectionClass(SchemaDeclare $schema)
    {
        $cTemplate = CollectionClassFactory::create($schema);

        $classFilePath = $this->buildClassFilePath($schema->getDirectory(), $cTemplate->getShortClassName());
        if ( ! file_exists($classFilePath) ||  $schema->isNewerThanFile( $classFilePath ) ) {
            if ( $this->writeClassTemplateToPath($cTemplate, $classFilePath, false) ) {
                return array( $cTemplate->getClassName() => $classFilePath );
            }
        }
    }


    /**
     * Write class template to the schema directory.
     *
     * @param string $directory The schema class directory.
     * @param ClassTemplate\ClassTemplate class template object.
     * @param boolean $overwrite Overwrite class file. 
     * @return array
     */
    public function writeClassTemplateToPath($cTemplate, $filepath, $overwrite = false) 
    {
        if ( ! file_exists($filepath) || $overwrite ) {
            file_put_contents( $filepath, $cTemplate->render() );
            return true;
        } elseif ( file_exists($filepath) ) {
            return true;
        }
        return false;
    }


    public function injectModelSchema($schema)
    {
        $model = $schema->getModel();

        $injection = new ClassInjection($model);
        $injection->read();
        $injection->removeContent();
        $injection->appendContent( "\t" . new ClassConst('schema_proxy_class', ltrim($schema->getSchemaProxyClass() ,'\\') ) );
        $injection->appendContent( "\t" . new ClassConst('collection_class',   ltrim($schema->getCollectionClass() ,'\\') ) );
        $injection->appendContent( "\t" . new ClassConst('model_class',        ltrim($schema->getModelClass() ,'\\') ) );
        $injection->appendContent( "\t" . new ClassConst('table',              ltrim($schema->getTable() ,'\\') ) );
        $injection->write();
        $refl = new ReflectionObject($model);
        return array( $schema->getModelClass() => $refl->getFilename() );
    }

    /**
     * Given a schema class list, generate schema files.
     *
     * @param array $classes class list or schema object list.
     * @return array class map array of schema class and file path.
     */
    public function generate($schemas)
    {
        // for generated class source code.
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            printf( "ERROR %s:%s  [%s] %s\n" , $errfile, $errline, $errno, $errstr );
        }, E_ERROR );

        // class map [ class => class file path ]
        $classMap = array();
        foreach( (array) $schemas as $schema ) {
            echo "Building ", get_class($schema) , "\n";

            // support old-style schema declare
            if ( $map = $this->generateSchemaProxyClass( $schema) ) {
                $classMap = $classMap + $map;
            }

            // collection classes
            if ( $map = $this->generateBaseCollectionClass( $schema ) ) {
                $classMap = $classMap + $map;
            }
            if ( $map = $this->generateCollectionClass( $schema ) ) {
                $classMap = $classMap + $map;
            }

            // in new schema declare, we can describe a schema in a model class.
            if( $schema instanceof \LazyRecord\Schema\DynamicSchemaDeclare ) {
                if ( $map = $this->injectModelSchema($schema) ) {
                    $classMap = $classMap + $map;
                }
            } else {
                if ( $map = $this->generateBaseModelClass($schema) ) {
                    $classMap = $classMap + $map;
                }
                if ( $map = $this->generateModelClass( $schema ) ) {
                    $classMap = $classMap + $map;
                }
            }
        }

        restore_error_handler();
        return $classMap;
    }
}

