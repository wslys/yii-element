<?php


namespace backend\modules\v1;


/**
 * Api v1 模块
 *
 * Class Module
 * @package api\models\v1
 */
class Module extends \yii\base\Module
{

    /**
     * 指定命名控制器
     * @var string
     */
    public $controllerNamespace = 'backend\modules\v1\controllers';

    /**
     * 初始化
     */
    public function init()
    {
        parent::init();
    }
}
