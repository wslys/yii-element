<?php
namespace api\modules\v2\controllers;

use api\base\controllers\ApiController;
use api\models\LoginForm;
use Yii;

class UsersController extends ApiController {
    public $modelClass = 'common\models\User';
	
	public function actionTest() {
		return ['code' => 100, 'msg' => 'Demo'];
	}

    /**
     * 登录
     * @return array
     */
    public function actionLogin()
    {
        $model = new LoginForm();
        if ($model->load(Yii::$app->request->getBodyParams(), '') && $model->login()) {
            return [
                'access_token' => $model->access_token,
            ];
        } else {
            return $model->getFirstErrors();
        }

        // return $this->success();
    }
    
}