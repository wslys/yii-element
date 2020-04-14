<?php
namespace backend\modules\v1\controllers;

use backend\modules\v1\base\controllers\ApiController;
use backend\modules\v1\models\LoginForm;
use Yii;

class UsersController extends ApiController {
    public $modelClass = 'common\models\User';

}