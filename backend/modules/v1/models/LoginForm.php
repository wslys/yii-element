<?php
namespace backend\modules\v1\models;

use common\models\User;
use common\unit\ToolFun;
use Yii;
use yii\base\Model;

/**
 * Login form
 */
class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;
    public $access_token = "";

    private $_user;


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        $this->getUser();
        if (!$this->hasErrors()) {
            if (!$this->_user) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
            if (!Yii::$app->security->validatePassword($this->password, $this->_user->password_hash)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     *
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        /*if ($this->validate()) {
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
        }
        
        return false;*/

        if ($this->validate()) {
            // 登录成功， 设置过期时间
            // $this->_user->expirer = time() + 600;

            $this->access_token = $this->_user->generateAccessToken();
            $this->_user->save();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    protected function getUser()
    {
        if ($this->_user === null) {
            $this->_user = User::findByUsername($this->username);
            if (!$this->_user) {
                $this->_user = User::findByPhone($this->username);
            }
        }

        return $this->_user;
    }
}
