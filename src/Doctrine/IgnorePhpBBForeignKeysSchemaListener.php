<?php
namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;


#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
class IgnorePhpBBForeignKeysSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        foreach($schema->getTables() as $table) {

            foreach($table->getForeignKeys() as $foreignKey) {

                if( str_contains($foreignKey->getReferencedTableName()->toString(), 'phpbb_') ) {
                    $table->dropForeignKey($foreignKey->getName());
                }
            }
        }
    }
}
