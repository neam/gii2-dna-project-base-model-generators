<?php
/**
 * @link http://neamlabs.com/
 * @copyright Copyright (c) 2015 Neam AB
 */

namespace neam\gii2_dna_project_base_generators;

use yii\base\Application;
use yii\base\BootstrapInterface;


/**
 * Class Bootstrap
 * @package neam\gii2_dna_project_base_generators
 * @author Fredrik Wollsén <fredrik@neam.se>
 */
class Bootstrap implements BootstrapInterface
{

    /**
     * Bootstrap method to be called during application bootstrap stage.
     *
     * @param Application $app the application currently running
     */
    public function bootstrap($app)
    {
        if ($app->hasModule('gii')) {
            if (!isset($app->getModule('gii')->generators['dna-project-base-yii1-model'])) {
                $app->getModule('gii')->generators['dna-project-base-yii1-model'] = 'neam\gii2_dna_project_base_generators\yii1_model\Generator';
            }
        }
    }
}