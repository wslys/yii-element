<?php
namespace backend\modules\v1\controllers;

use backend\modules\v1\base\controllers\ApiController;
use backend\modules\v1\models\LoginForm;
use common\models\User;
use common\unit\ToolFun;
use Yii;

class UserController extends ApiController {

    /**
     * 登录
     * @return array
     */
    public function actionLogin()
    {
        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post(), '') && $model->login()) {
            return [
                'token' => $model->access_token,
            ];
        } else {
			return ['message'=>ToolFun::getModelError($model)];
        }
    }

    // 用户信息
    public function actionInfo() {
        return [
            "roles" => ['admin'],
            "introduction" => 'I am a super administrator',
            "avatar" => 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
            "name" => 'Super Admin'
        ];
    }

    // 用户信息
    public function actionList() {
        return User::find()->asArray()->limit(50)->all();
    }

    public function actionRegister() {
        return $this->success(['msg' => '用户注册接口待开发']);
    }

}