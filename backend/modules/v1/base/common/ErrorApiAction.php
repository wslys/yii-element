<?php
namespace backend\modules\v1\base\common;

use Yii;
use yii\web\ErrorAction;
use yii\web\Response;

class ErrorApiAction extends ErrorAction
{
    public function run()
    {
        $this->id = Yii::$app->controller->action->id;
        $this->controller = Yii::$app->controller->id;;
        // 根据异常类型设定相应的响应码
        Yii::$app->getResponse()->setStatusCodeByException($this->exception);
        // json 格式返回
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        // 返回的内容数据
        return [
            'msg' => $this->exception->getMessage(),
            'err' => $this->exception->getCode()
        ];
    }
}