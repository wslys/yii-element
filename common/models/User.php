<?php
namespace common\models;

use common\unit\ToolFun;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string  $username
 * @property string  $phone
 * @property string  $openid
 * @property string  $pub_openid
 * @property string  $password_hash
 * @property string  $password_reset_token
 * @property string  $auth_key
 * @property string  $access_token
 * @property integer $status
 * @property integer $type
 * @property integer $prop
 * @property integer $charge_prop
 * @property integer $is_open_tmpmsg
 * @property string  $person
 * @property string  $person_phone
 * @property string  $comment
 * @property string  $cuid
 * @property string  $appid
 * @property string  $pub_appid
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $expirer
 * @property integer $is_auto_loan
 * @property integer $auto_loan_fee
 * @property integer $is_more_wxlogin
 * @property integer $prop_type
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_ACTIVE  = 10;
    const ADMIN_USER = 1; // 管理员商户类型
    const DL_USER    = 2; // 代理商户类型
    const HELP_USER  = 3; // 代理助手用户类型
    const INTRO_USER = 4; // 介绍人用户类型
    const HOTEL_USER = 5; // 店长用户类型
    const BH_USER    = 6; // 补货员用户类型
    const SV_USER    = 7; // 服务商用户类型

    const IS_OPEN_TMPMSG  = 1; // 开启补货通知
    const IS_AUTO_LOAN_OK = 1; // 开启自动转账
    const IS_AUTO_LOAN_NO = 0; // 不开启自动转账

    // 默认小程序
    const DEF_WXAPP_NAME  = '橙密智能售套机'; // 小程序名称
    const DEF_WXAPP_APPID = 'wx9dccec5d2d309a32'; // 小程序AppId
    const DEF_WXAPP_SECRET= 'e2ccd3c68d76a1f918307eaeea939f6c'; // 小程序Secret

    const IS_MORE_WXLOGIN = 0; // 是否开启多微信绑定登录

    const PROP_TYPE_PACK  = 0; // 用户打包分成
    const PROP_TYPE_FIXED = 1; // 用户固定分成

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'username'    => '用户名',
            'phone'       => '手机号',
            'openid'      => 'OpenID',
            'auth_key'    => 'Auth Key',
            'password_hash'=> '密码',
            'is_auto_loan' => '是否开启自动转账',
            'auto_loan_fee'=> '自动转账金额',
            'password_reset_token' => 'Password Reset Token',
            'type'        => '账户类型',
            'status'      => '状态',
            'prop'        => '分成比',
            'charge_prop' => '充电分成比',
            'prop_type'   => '用户分成类别',
            'person'      => '联系人',
            'person_phone'=> '联系电话',
            'comment'     => '备注',
            'cuid'        => '创建者',
            'appid'       => '小程序AppID',
            'pub_appid'   => '公众号AppID',
            'create_time' => '创建时间',
            'update_time' => '更新时间'
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['username', 'password_hash'], 'required'],
            ['status', 'default','value'  => self::STATUS_ACTIVE],
            ['status', 'in',     'range'  => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
            ['prop',   'number'],
            ['cuid',   'number'],
            ['phone',  'number'],
            // [['prop'], 'verifPropRules'], // TODO 自动校验，是否超过父级分成比我发完成校验
            ['phone',  'unique', 'targetClass' => '\common\models\User', 'message' => '手机号已被使用'],
            ['username','unique','targetClass' => '\common\models\User', 'message' => '用户名已经存在'],
        ];
    }

    /**
     * 保存、更新前
     *   处理创建时间以及更新时间
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        // 用户创建时间
        $this->isNewRecord && $this->created_at = time();
        // 用户创建者
        $this->isNewRecord && $this->cuid = Yii::$app->user->identity->getId();
        if($insert) {
            $this->updated_at = time();
        }
        return parent::beforeSave($insert);
    }

    // 获取能够参与分钱的用户类型 【暂时 脚本命令处使用】
    public static function getCanGetFeeUserType() {
        return [User::ADMIN_USER, User::DL_USER, User::INTRO_USER, User::SV_USER, User::HOTEL_USER];
    }

    // 用户小程序
    public function getWxapp() {
        // return $this->hasOne(Wxapp::className(), ['appid'=>'appid']);
        $topDlUser = $this->getTopDlUser();
        $wxapp = null;
        if ($topDlUser) {
            $wxapp = Wxapp::find()->where(['appid'=>$topDlUser->appid])->one();
        }

        return $wxapp;
    }
    // 配置小程序
    public function confUserWxapp() {
        $wxapp = $this->wxapp;
        $appid  = '';
        if (isset($wxapp['appid']) && $wxapp['appid']) {
            $appid  = $wxapp['appid'];
        }
        $secret = '';
        if (isset($wxapp['secret']) && $wxapp['secret']) {
            $secret  = $wxapp['secret'];
        }

        if ($appid && $secret) {
            // 小程序
            Yii::$app->params['wechatMiniProgramConfig']['app_id'] = $appid;
            Yii::$app->params['wechatMiniProgramConfig']['secret'] = $secret;

            // 商户号
            if (isset($wxapp['mchid']) && isset($wxapp['apikey']) && isset($wxapp['cert_pem']) && isset($wxapp['key_pem'])) {
                Yii::$app->params['wechatPaymentConfig']['app_id'] = $appid;
                Yii::$app->params['wechatPaymentConfig']['mch_id'] = $wxapp['mchid'];
                Yii::$app->params['wechatPaymentConfig']['key']    = $wxapp['apikey'];

                // 检查此文件是否存在， 若不存在，从新写入
                $cert_pem_file= $wxapp['cert_pem'];
                $cert_pem_val = $wxapp['cert_pem_val'];
                if ($cert_pem_file && !file_exists($cert_pem_file)) {
                    file_put_contents($cert_pem_file,  $cert_pem_val);
                }
                $key_pem_file = $wxapp['key_pem'];
                $key_pem_val  = $wxapp['key_pem_val'];
                if ($key_pem_file && !file_exists($key_pem_file)) {
                    file_put_contents($key_pem_file,  $key_pem_val);
                }
                Yii::$app->params['wechatPaymentConfig']['cert_path'] = $cert_pem_file;
                Yii::$app->params['wechatPaymentConfig']['key_path']  = $key_pem_file;
            }
        }
    }
    // 补货二维码链接
    public function getCpfrQrcodeLink() {
        $cpfrUrlPrefix = "https://ttj.mjyun.com/xcx/bh/";
        $wxapp = $this->wxapp;
        if ($wxapp && isset($wxapp['cpfr_qrcode_rule'])) {
            $cpfrUrlPrefix = $wxapp['cpfr_qrcode_rule'];
        }

        // 补货二维码
        UserCpfrQrcode::InitUserCpfrQrcode($this->id, UserCpfrQrcode::TYPE_CPFR);
        return $cpfrUrlPrefix . $this->cpfrQrcode->qr_code;
    }
    // 登录二维码链接
    public function getLoginQrcodeLink() {
        //return $this->hasOne(UserCpfrQrcode::className(), ['uid'=>'id'])->where(['valid'=>UserCpfrQrcode::VALID_OK, 'type'=>UserCpfrQrcode::TYPE_LOGIN]);
        $loginUrlPrefix = "https://" . $_SERVER['SERVER_NAME'] . "/site/bind-wx/";

        // 登录二维码
        UserCpfrQrcode::InitUserCpfrQrcode($this->id, UserCpfrQrcode::TYPE_LOGIN);

        return $loginUrlPrefix . $this->loginQrcode->qr_code;
    }
    // 补货通知二维码链接
    public function getCpfrNotifyQrcodeLink() {
        //return $this->hasOne(UserCpfrQrcode::className(), ['uid'=>'id'])->where(['valid'=>UserCpfrQrcode::VALID_OK, 'type'=>UserCpfrQrcode::TYPE_CPFR_NOTIFY]);
        $cpfrNotifyUrlPrefix = "https://" . $_SERVER['SERVER_NAME'] ."/wx-cpfr/bind-cpfr-notify/";

        // 登录二维码
        UserCpfrQrcode::InitUserCpfrQrcode($this->id, UserCpfrQrcode::TYPE_CPFR_NOTIFY);
        return $cpfrNotifyUrlPrefix . $this->cpfrNotifyQrcode->qr_code;
    }
    // 设备二维码规则前缀链接
    public function getDvidQrcodeLinkPrefix() {
        $cpfrUrlPrefix = "https://ttj.mjyun.com/xcx/dvid/";
        $wxapp = $this->wxapp;
        if ($wxapp && isset($wxapp['dvid_qrcode_rule'])) {
            $cpfrUrlPrefix = $wxapp['dvid_qrcode_rule'];
        }
        return $cpfrUrlPrefix;
    }

    // 获取用户关系链
    public static function getUserRelationUsers($uid='', &$list=[]) {
        $userRelationIds = self::getUserRelationUids($uid);

        $rows = User::find()->where(['id'=>$userRelationIds])->all();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => $row->id,
                'pid' => $row->id!=1?$row->pUser['id']:-1,
                'username' => $row->username,
                'typestr' => $row->typeStr['text'],
                'nowuid' => $row->id == $uid ? 1 : 0,
                'prop_str' => ($row->prop_type ? '【固定】' : '【打包】') . ('购物：' . $row->prop . '% 充电：' . $row->charge_prop . '%'),
            ];
        }

        return $list;
    }

    // 用户公众号
    public function getWxpub() {
        // return $this->hasOne(Wxpub::className(), ['appid'=>'pub_appid']);
        $topDlUser = $this->getTopDlUser();
        $wxpub = null;
        if ($topDlUser) {
            $wxpub = Wxpub::find()->where(['appid'=>$topDlUser->pub_appid])->one();
        }

        return $wxpub;
    }
    // 配置公众号
    public function confUserWxpub() {
        $wxpub = $this->wxpub;
        if ($wxpub) {
            if (isset($wxpub['appid']) && isset($wxpub['secret'])) {
                Yii::$app->params['wechatConfig']['app_id'] = $wxpub['appid'];
                Yii::$app->params['wechatConfig']['secret'] = $wxpub['secret'];
                Yii::$app->params['wechatConfig']['token']  = $wxpub['token'];
                Yii::$app->params['wechatConfig']['aes_key']= $wxpub['aes_key'];

                return [
                    'appid' => $wxpub['appid'],
                    'utmpid'  => isset($wxpub['cpfr_tmpid']) ? $wxpub['cpfr_tmpid'] : '',
                    'order_tmpid' => isset($wxpub['order_tmpid']) ? $wxpub['order_tmpid'] : '',
                    'oog_err_tmpid' => isset($wxpub['oog_err_tmpid']) ? $wxpub['oog_err_tmpid'] : '',
                    'user_fee_tmpid' => isset($wxpub['user_fee_tmpid']) ? $wxpub['user_fee_tmpid'] : '',
                ];
            }
        }
    }

    // 更新用户小程序
    public function editUserWxapp($appid='') {
        if (!$appid) {
            $appid = '';
        }
        if ($this->pUser->type != self::ADMIN_USER) {
            return '只有一级代理才能配置小程序';
        }

        $this->appid = $appid;
        if ($this->save()) {
            return 'OK';
        }
        return ToolFun::getModelError($this);
    }
    // 更新用户公众号
    public function editUserWxpub($appid='') {
        if (!$appid) {
            $appid = '';
        }
        if ($this->pUser->type != self::ADMIN_USER) {
            return '只有一级代理才能配置公众号';
        }

        $this->pub_appid = $appid;
        if ($this->save()) {
            return 'OK';
        }
        return ToolFun::getModelError($this);
    }

    // TODO 获取用户酒店分成
    public function getHotelProps() {
        // return HotelProp::find()->where(['uid'=>$this->id])->all();
        // return $this->hasMany(HotelProp::className(), ['uid' => 'id']);
        return $this->hasMany(HotelProp::className(), ['uid' => 'id'])
            ->where('is_delete = :is_delete', [':is_delete' => 0]);
    }

    /**
     * @inheritdoc
     */
    public function getUserType()
    {
        return $this->hasOne(UserType::className(), ['id' => 'type']);
    }

    // 获取用户直属下级用户ID
    public static function getDownUid($type='', $uid='') {
        $sql = "
            SELECT * FROM shj_user WHERE id IN ( 
                SELECT uid FROM shj_user_relation WHERE puid=$uid 
            ) AND status=10;
        ";
        if ($type && !is_array($type)) {
            $sql = "
                SELECT * FROM shj_user WHERE type=$type AND id IN ( 
                    SELECT uid FROM shj_user_relation WHERE puid=$uid 
                ) AND status=10;
            ";
        }
        if (is_array($type) && count($type)>0) {
            $type = implode(",", $type);
            $sql = "
                SELECT * FROM shj_user WHERE type IN ($type) AND id IN ( 
                    SELECT uid FROM shj_user_relation WHERE puid=$uid 
                ) AND status=10;
            ";
        }

        $rows = $rows = Yii::$app->db->createCommand($sql)->queryAll();

        $uids = [];
        foreach ($rows as $row) {
            $uids[] = $row['id'];
        }

        return $uids;
    }

    /**
     * 用户所有资源
     * @return array
     */
    public function getSources()
    {
        $type = $this->userType;
        $sources = $type->sources;

        $value = [];
        foreach ($sources as $source) {
            $value[] = $source['value'];
        }

        return $value;
    }
    /**
     * 所有资源
     * @return array
     */
    public function getAllSources()
    {
        $sources = (new OurSource())->allSource();

        $value = [];
        foreach ($sources as $source) {
            $value[] = $source['value'];
        }

        return $value;
    }

    // 是否有权访问
    /**
     * 权限检查
     * @return bool
     */
    public function hasAuthor($route='') {
        empty($route) && $route = Yii::$app->controller->getRoute();

        return in_array($route, $this->sources);
    }

    /**
     * 直属子用户
     * @return \yii\db\ActiveQuery
     */
    public function getChardUser(){
        return $this->hasMany(User::className(), ['id' => 'uid'])
            ->viaTable('shj_user_relation', ['puid' => 'id'])->where(['status'=>self::STATUS_ACTIVE]);
    }

    /**
     * 获取用户顶级代理
     * @return $this|User|null
     */
    public function getTopDlUser($uid='') {
        !$uid && $uid = $this->id;
        $user = User::findOne($uid);

        if ($user->type == self::ADMIN_USER) {
            return null;
        }
        if ($user->type == self::DL_USER && $user->pUser->type == self::ADMIN_USER) {
            return $user;
        }else {
            $model = UserRelation::findOne(['uid'=>$user->id]);
            if ($model) {
                $nexUser = User::findOne($model->dlid);
                if ($nexUser->type == self::DL_USER && $nexUser->pUser->type == self::ADMIN_USER) {
                    return $nexUser;
                }elseif ($model->dlid == $uid) {
                    return $this->getTopDlUser($model->puid);
                }else {
                    return $this->getTopDlUser($model->dlid);
                }
            }
        }
    }
    /**
     * 获取直属上级
     * @return \yii\db\ActiveQuery
     */
    public function getPUser() {
        return $this->hasOne(User::className(), ['id' => 'puid'])
            ->viaTable('shj_user_relation', ['uid' => 'id']);
    }
    /**
     * 获取所属代理
     * @return \yii\db\ActiveQuery
     */
    public function getDlUser() {
        return $this->hasOne(User::className(), ['id' => 'dlid'])
            ->viaTable('shj_user_relation', ['uid' => 'id']);
    }

    // 获取用户的上级代理【此函数不可轻易修改】
    public static function getUserPDlId($uid='') {
        if (!$uid) {
            return 1;
        }
        $user = self::findOne($uid);
        if (!$user) {
            return 1;
        }

        if ($user->type == self::DL_USER || $user->type == self::ADMIN_USER) {
            return $user->id;
        }

        /*$userRelation = $user->userRelation;
        if ($userRelation['puid']) {
            return self::getUserPDlId($userRelation['puid']);
        }else {
            return 1;
        }*/

        $model = UserRelation::findOne(['uid'=>$uid]);
        if (!$model) {
            return 1;
        }

        return $model->dlid?$model->dlid:1;
    }
    // 获取用户的上级服务商
    public static function getUserPSvId($uid) {
        if (!$uid) {
            return 0;
        }
        $user = self::findOne($uid);
        if (!$user) {
            return 0;
        }

        if ($user->type == self::SV_USER) {
            return $user->id;
        }

        /*$userRelation = $user->userRelation;
        if ($userRelation['puid']) {
            return self::getUserPSvId($userRelation['puid']);
        }else {
            return 0;
        }*/
        $model = UserRelation::findOne(['uid'=>$uid]);
        if (!$model) {
            return 0;
        }

        return $model->puid?$model->puid:0;
    }

    // 获取用户对应的店长用户[直属，既店长创建者]
    public function getUserHotelUser() {
        return $this->hasMany(User::className(), ['id' => 'uid'])
            ->viaTable('shj_user_relation', ['puid' => 'id'])
            ->where(['status'=>self::STATUS_ACTIVE, 'type'=>self::HOTEL_USER]);
    }

    /**
     * 用户关系关联
     * @return \yii\db\ActiveQuery
     */
    public function getUserRelation() {
        return $this->hasOne(UserRelation::className(), ['uid' => 'id']);
    }

    // 获取当前登录的下级用户--通过用户类型查找
    public static function downUserByType2($type) {
        !is_array($type) && $type = [$type];
        $user = Yii::$app->user->identity;
        $rows = User::find()->where(['type'=>$type, 'status'=>self::STATUS_ACTIVE])
            ->andWhere([
                'OR',
                ['cuid'=>$user->id, 'status'=>self::STATUS_ACTIVE],
                ['id'=>UserRelation::find()->select('uid')->where(['puid'=>$user->id])]
            ])->all();

        return $rows;
    }
    // 获取当前登录的下级用户--通过用户类型查找
    public static function downUserByType($type, $uid='') {
        !is_array($type) && $type = [$type];
        if (!$uid) {
            $user = Yii::$app->user->identity;
        }else {
            $user = User::findOne($uid);
        }

        $query = User::find()->where(['type'=>$type, 'status'=>self::STATUS_ACTIVE]);

        if ($user->type != User::ADMIN_USER) {
            $w = ['OR', ['dlid'=>$user->getId()], ['puid'=>$user->getId()]];
            if ($user->type == User::SV_USER) {
                $w = ['puid'=>$user->getId()];
            }
            $query->andWhere([ 'id' => UserRelation::find()->select('uid')->where($w) ]);
        }
        $rows = $query->all();
        return $rows;
    }

    /**
     * 酒店用户关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getHotels() {
//        return $this->hasMany(Hotel::className(), ['id' => 'hid'])
//            ->viaTable('shj_user_hotel', ['uid' => 'id'])
//            ->where(['is_delete'=>UserHotel::IS_DELETE_NO])
//            ->andWhere(['<>', 'type', Hotel::TYPE_DEFAULT]);
//
        return Hotel::find()->where(['id'=>UserHotel::find()->select('hid')->where(['uid'=>$this->id])])
            ->andWhere(['is_delete'=>Hotel::IS_DELETE_NO])
            ->andWhere(['<>', 'type', Hotel::TYPE_DEFAULT])
            ->all();
    }
    // 通过用户ID获取店长介绍人
    public static function getDzJsrUserByDl($uid='') {
        if (!$uid) {
            return [];
        }
        return User::find()->where(['cuid'=>$uid, 'type'=>[User::HOTEL_USER, User::HOTEL_USER]])->all();
    }
    public function getHotelIds() {
        $hotels = $this->hotels;
        $ids = [];
        foreach ($hotels as $hotel) {
            $ids[] = $hotel['id'];
        }
        return $ids;
    }
    public function getHotelNames() {
        $hotels = $this->hotels;
        $names = [];
        foreach ($hotels as $hotel) {
            $names[] = $hotel['name'];
        }
        return $names;
    }
    public function getHotelNamesTypes() {
        if ($this->type == self::BH_USER) {
            $hotels = $this->cpfrHotels;
        }else {
            $hotels = $this->hotels;
        }

        $names = [];
        foreach ($hotels as $hotel) {
            $names[] = $hotel['name'].'['.$hotel->hotelType['name'].']';
        }
        return $names;
    }
    /** 补货员酒店 **/
    public function getCpfrHotels() {
        return $this->hasMany(Hotel::className(), ['id' => 'hid'])
            ->viaTable('shj_user_cpfr_hotel', ['uid' => 'id'])
            ->where(['is_delete'=>UserCpfrHotel::IS_DELETE_NO])
            ->andWhere(['<>', 'type', Hotel::TYPE_DEFAULT]);
    }
    public function getCpfrHotelNames() {
        $hotels = $this->cpfrHotels;
        $names = [];
        foreach ($hotels as $hotel) {
            $names[] = $hotel['name'];
        }
        return $names;
    }

    // 用户默认酒店
    public function getUserDefHotel() {
        return $this->hasOne(Hotel::className(), ['id' => 'hid'])
            ->viaTable('shj_user_hotel', ['uid' => 'id'])
            ->where(['is_delete'=>UserHotel::IS_DELETE_NO, 'shj_hotel.type'=>Hotel::TYPE_DEFAULT]);
    }

    // 补货二维码
    public function getCpfrQrcode() {
        return $this->hasOne(UserCpfrQrcode::className(), ['uid'=>'id'])->where(['valid'=>UserCpfrQrcode::VALID_OK, 'type'=>UserCpfrQrcode::TYPE_CPFR]);
    }

    // 登录二维码
    public function getLoginQrcode() {
        return $this->hasOne(UserCpfrQrcode::className(), ['uid'=>'id'])->where(['valid'=>UserCpfrQrcode::VALID_OK, 'type'=>UserCpfrQrcode::TYPE_LOGIN]);
    }

    // 补货通知二维码
    public function getCpfrNotifyQrcode() {
        return $this->hasOne(UserCpfrQrcode::className(), ['uid'=>'id'])->where(['valid'=>UserCpfrQrcode::VALID_OK, 'type'=>UserCpfrQrcode::TYPE_CPFR_NOTIFY]);
    }

    /**
     * @return array
     */
    public function getTypeStr() {
        $res = ['value'=>$this->type, 'text'=>''];
        if ($this->type == self::ADMIN_USER) {
            $res = ['value'=>$this->type, 'text'=>'Admin'];
            $res['text'] = 'Admin';
        }elseif ($this->type == self::DL_USER) {
            $res['text'] = '代理';
        }elseif ($this->type == self::HELP_USER) {
            $res['text'] = '代理助手';
        }elseif ($this->type == self::INTRO_USER) {
            $res['text'] = '介绍人';
        }elseif ($this->type == self::HOTEL_USER) {
            $res['text'] = '店长';
        }elseif ($this->type == self::BH_USER) {
            $res['text'] = '补货员';
        }elseif ($this->type == self::SV_USER) {
            $res['text'] = '服务商';
        }

        return $res;
    }
    public static function TypeArr($type) {
        $res = ['value'=>0, 'text'=>''];
        if ($type == self::ADMIN_USER) {
            $res = ['value'=>$type, 'text'=>'Admin'];
        }elseif ($type == self::DL_USER) {
            $res = ['value'=>$type, 'text'=>'代理'];
        }elseif ($type == self::HELP_USER) {
            $res = ['value'=>$type, 'text'=>'代理助手'];
        }elseif ($type == self::INTRO_USER) {
            $res = ['value'=>$type, 'text'=>'介绍人'];
        }elseif ($type == self::HOTEL_USER) {
            $res = ['value'=>$type, 'text'=>'店长'];
        }elseif ($type == self::BH_USER) {
            $res = ['value'=>$type, 'text'=>'补货员'];
        }elseif ($type == self::SV_USER) {
            $res = ['value'=>$type, 'text'=>'服务商'];
        }
        return $res;
    }

    /**
     * 获取能够参与分账的用户类型
     * @return array
     */
    public static function splitFeeUserType() {
        return [self::DL_USER, self::INTRO_USER, self::HOTEL_USER, self::SV_USER];
    }

    // 获取用户下级最大分成比例
    public static function getUserDownMaxProp($uid='') {
        $userStatus = User::STATUS_ACTIVE;
        $sql = "
            SELECT MAX(prop) prop,  MAX(charge_prop) charge_prop FROM shj_user WHERE (
                shj_user.cuid=$uid OR id IN (
                    SELECT uid FROM shj_user_relation WHERE puid=$uid 
                )
            ) AND shj_user.status=$userStatus
        ";

        $row = Yii::$app->db->createCommand($sql)->queryOne();

        $prop        = isset($row['prop'])        && $row['prop']        ? $row['prop'] : 0;
        $charge_prop = isset($row['charge_prop']) && $row['charge_prop'] ? $row['charge_prop'] : 0;
        return [
            'prop' => $prop,
            'charge_prop' => $charge_prop
        ];
    }
    // 获取用户上级分成比
    public static function getUserUpMaxProp($uid='') {
        $user_relation = UserRelation::findOne(['uid'=>$uid, 'is_delete'=>UserRelation::IS_DELETE_NO]);
        if (!$user_relation) {
            return 0;
        }

        $user = self::findOne($user_relation->puid);
        if (!$user) {
            return 0;
        }

        return [
            'prop' => $user->prop ? $user->prop : 0,
            'charge_prop' => $user->charge_prop ? $user->charge_prop : 0,
        ];
    }

    // 获取用户的最大分成比例+最小分成比例 【若没有uid，则最小分成比未0， 若没有puid，则最大分成比为100】
    public static function getUserMaxPropMinProp($uid='', $puid='', $prop_type=0) {
        $res = [
            'prop'       => 0,
            'chargeProp' => 0,

            'minProp'      => 0,
            'minChargeProp'=> 0,

            'maxProp'      => 100,
            'maxChargeProp'=> 100
        ];
        if ($uid) {
            if (!$prop_type) {
                $sql = "SELECT MAX(uprop) prop,  MAX(ucprop) charge_prop 
                        FROM  shj_hotel_prop 
                        WHERE shj_hotel_prop.uid IN ( SELECT uid FROM shj_user_relation WHERE puid=$uid ) 
                          AND shj_hotel_prop.is_delete=0";

                $row = Yii::$app->db->createCommand($sql)->queryOne();
                $prop = isset($row['prop']) && $row['prop'] ? $row['prop'] : 0;
                $charge_prop = isset($row['charge_prop']) && $row['charge_prop'] ? $row['charge_prop'] : 0;

                $res['minProp'] = $prop;
                $res['minChargeProp'] = $charge_prop;
            }
        }

        if ($puid>0) {
            $_puser = User::findOne($puid);
            if (!$_puser) {
                return $res;
            }
            if (!$_puser->prop_type) {
                $res['maxProp'] = $_puser->prop;
                $res['maxChargeProp'] = $_puser->charge_prop;
                return $res;
            }
            if ($uid && $prop_type) { // 修改
                // 自己直属上级 - 自己的最大下级 = 最大可修改的分成比例
                $sql = "SELECT hid FROM shj_hotel_prop 
                            LEFT JOIN shj_hotel ON shj_hotel_prop.hid=shj_hotel.id
                            WHERE shj_hotel_prop.uid=$uid 
                            AND shj_hotel_prop.is_delete=0 
                            AND shj_hotel.is_delete=0 
                            ORDER BY shj_hotel_prop.prop DESC, shj_hotel_prop.id DESC limit 1 
                            ";

                $row = Yii::$app->db->createCommand($sql)->queryOne();
                $_hid = isset($row['hid']) ? $row['hid'] : '';
                $_dlid = User::getUserPDlId($uid);

                $dlHotelPropRec = HotelProp::find()->where(['hid'=>$_hid, 'uid'=>$_dlid, 'is_delete'=>HotelProp::IS_DELETE_NO])->one();

                $_user = User::findOne($uid);
                $usHotelPropRec = null;
                if ($_user->type != User::DL_USER) {
                    $usHotelPropRec = HotelProp::find()->where(['hid'=>$_hid, 'uid'=>$uid,   'is_delete'=>HotelProp::IS_DELETE_NO])->one();
                }

                $res['maxProp'] = $dlHotelPropRec->prop + (isset($usHotelPropRec)?$usHotelPropRec->prop:0);
                $res['maxChargeProp'] = $dlHotelPropRec->charge_prop + (isset($usHotelPropRec)?$usHotelPropRec->charge_prop:0);
            }elseif ($uid) {
                // 自己直属上级 - 自己的最大下级 = 最大可修改的分成比例
                $sql = "SELECT hid FROM shj_hotel_prop 
                            LEFT JOIN shj_hotel ON shj_hotel_prop.hid=shj_hotel.id
                            WHERE shj_hotel_prop.uid=$uid 
                            AND shj_hotel_prop.is_delete=0 
                            AND shj_hotel.is_delete=0 
                            ORDER BY shj_hotel_prop.prop DESC, shj_hotel_prop.id DESC limit 1 
                            ";

                $row = Yii::$app->db->createCommand($sql)->queryOne();
                $_hid = isset($row['hid']) ? $row['hid'] : '';
                $_dlid = User::getUserPDlId($uid);

                $dlHotelPropRec = HotelProp::find()->where(['hid'=>$_hid, 'uid'=>$_dlid, 'is_delete'=>HotelProp::IS_DELETE_NO])->one();

                $_user = User::findOne($uid);
                $usHotelPropRec = HotelProp::find()->where(['hid'=>$_hid, 'uid'=>$uid,   'is_delete'=>HotelProp::IS_DELETE_NO])->one();
                if ($_user->type == User::DL_USER) {
                    $_dlid = $_user->pUser->id;
                    $dlHotelPropRec = HotelProp::find()->where(['hid'=>$_hid, 'uid'=>$_dlid, 'is_delete'=>HotelProp::IS_DELETE_NO])->one();
                }

                $res['maxProp'] = $dlHotelPropRec->prop + (isset($usHotelPropRec)?$usHotelPropRec->prop:0);
                $res['maxChargeProp'] = $dlHotelPropRec->charge_prop + (isset($usHotelPropRec)?$usHotelPropRec->charge_prop:0);
            }else { // 添加
                // 上级用户的直属代理的利润
                // 1 取上级用户的del酒店
                if ($puid != 1) {
                    $_user = self::findOne($puid);
                    $_hotel = $_user->userDefHotel;

                    // 2 取直属代理在该默认酒店上的利润
                    $_dlid = User::getUserPDlId($_user->id);
                    $dlHotelPropRec = HotelProp::find()->where(['hid'=>$_hotel->id, 'uid'=>$_dlid, 'is_delete'=>HotelProp::IS_DELETE_NO])->one();

                    $res['maxProp'] = $dlHotelPropRec->prop;
                    $res['maxChargeProp'] = $dlHotelPropRec->charge_prop;
                }
            }
        }

        return $res;
    }

    /**
     * 创建账号
     * @param $data
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public static function create($data) {
        $id = isset($data['id'])?$data['id']:'';

        $user = self::findOne($id);
        if (!$user) {
            $user = new self();
        }else {
            // 分成比检查
//            $downProp = self::getUserDownMaxProp($id);
//
//            if ($data['prop_type']) {
//                $pu = $user->pUser;
//                if ( ($downProp['prop'] + $data['prop']) > $pu['prop'] ) {
//                    return ['code'=>1, 'msg'=>'1固定购物比例不能大于：' . ($pu['prop'] - $downProp['prop']) . '%。'];
//                }
//                if ( ($downProp['charge_prop'] + $data['charge_prop']) > $pu['charge_prop'] ) {
//                    return ['code'=>1, 'msg'=>'2固定充电比例不能大于：' . ($pu['charge_prop'] - $downProp['charge_prop']) . '%。'];
//                }
//            }else {
//                // 如果有下级用户，检查下级用户最大分成比例， 不得小于最大分成比例
//                if (isset($data['prop'])) {
//                    if ($data['prop'] < $downProp['prop']) {
//                        return ['code'=>1, 'msg'=>'3购物分成不得低于下级分成比：' . $downProp['prop'] . '%。'];
//                    }
//                }
//                if (isset($data['charge_prop'])) {
//                    if ($data['charge_prop'] < $downProp['charge_prop']) {
//                        return ['code'=>1, 'msg'=>'4充电分成不得低于下级分成比：' . $downProp['charge_prop'] . '%。'];
//                    }
//                }
//            }
        }

        $transaction = self::getDb()->beginTransaction();
        try {
            $user->username = trim($data['username']);

            if (isset($data['password']) && $data['password']) {
                $user->setPassword(trim($data['password']));
            }else {
                if ($user->isNewRecord) { // 系统初始密码（如：1104【解释：11-04】）
                    $user->setPassword('CM' . date('mdHis'));
                }
            }
            //$user->person = trim($data['person']);
            $user->phone = trim($data['person_phone']);
            $user->person_phone = trim($data['person_phone']);
            $user->type = trim($data['type']);
            $user->prop_type = trim($data['prop_type']);
            $user->prop = trim(empty($data['prop'])?0:$data['prop']);
            if (isset($data['charge_prop'])) {
                $user->charge_prop = trim(empty($data['charge_prop'])?0:$data['charge_prop']);
            }else {
                $user->charge_prop = $user->prop;
            }
            $user->comment = trim($data['comment']);
            $user->verifPropRules2(User::getUserMaxPropMinProp($user->id, $data['puid'], $user->prop_type));
            $user->generateAuthKey();
            $user->generatePasswordResetToken();
            if (!$user->save()) {
                return ['code'=>1, 'msg'=>self::getModelError($user)];
            }
            if ($user->status == User::STATUS_DELETED.'') {
                $transaction->commit();
                return ['code'=>0, 'msg'=>'success', 'uid'=>$user->id];
            }

            // 用户关系
            $dlId = User::getUserPDlId($data['puid']);
            if (!UserRelation::create($user->id, trim($data['puid']), trim($data['type']), $dlId)) {
                // 删除用户信息
                return ['code'=>2, 'msg'=>'用户关系创建失败'];
            };

            if ($user->type != self::BH_USER) {
                // 创建/更新用户默认酒店
                $addUserDefHotelStatus = Hotel::addUserDefHotel($user->id, $user->type);
                if ($addUserDefHotelStatus != 'OK') {
                    return ['code'=>3, 'msg'=>$addUserDefHotelStatus];
                }

                // 酒店关联
                $userHotelStatus = UserHotel::ResetCreate($user->id, $data['hotel_ids']);
                if ($userHotelStatus != 'OK') {
                    return ['code'=>4, 'msg'=>$userHotelStatus];
                };

                // 更新用户酒店分成比例
                HotelProp::updateUserHotelProp($user->id);
            }else {
                // 创建/修改补货员信息
                $status = UserCpfrHotel::updateUserCpfrHotel($user->id, $data['hotel_ids']);
                if ($status != 'OK') {
                    return $status;
                }
            }

            $transaction->commit();
            return ['code'=>0, 'msg'=>'success', 'uid'=>$user->id];
        } catch(\Exception $e) {
            $transaction->rollBack();
            return ['code'=>10, 'msg'=>$e->getMessage()];
        }
    }

    public static function getModelError($model) {
        $errors = $model->getErrors();
        if (!is_array($errors)) return '';

        $fistError = array_shift($errors);
        if (!is_array($fistError)) return '';

        return array_shift($fistError);
    }

    /**
     * 设置用户status，
     *
     * @param int $status
     * @return bool
     */
    public function setStatusVal($status=10) {
        // 有下级用户， 不能删除
        if (count($this->chardUser) > 0) {
            return '该账户有下级用户,不能删除';
        }

        $data = Capital::data($this->id);
        if ($data['balance'] > 0) {
            return '该账户还有账户余额,不能删除';
        }

        if ($this->type == self::DL_USER) {
            // 检查是否有设备
            $dvids = UserDvidRelation::getDlDvids($this->id);
            if (count($dvids) > 0) {
                return '代理账户删除时,若有设备,需要先回收';
            }
        }

        // 查看用户是否有管理的设备
        $userTotalDvidNum = UserDvidRelation::userTotalDvidNum($this->id);
        if (!$userTotalDvidNum && $userTotalDvidNum>0) {
            return '用户下有设备，请先回收或转移';
        }

        // 不含默认酒店
        $uhids = Hotel::getHotelIdsByUid($this->id);
        if ($uhids && $uhids > 0) {
            return '用户下有授权酒店，请先取消授权';
        }

        // 删除用户下所有酒店关系【含默认酒店】
        // UserHotel::DlUserHotel($this->id);
        // 删除用户Hotel_prop 酒店分成
        // HotelProp::deleteUserHotelPropByUid($this->id);

        $this->status = $status;
        if ($this->save()) {
            return 'OK';
        }
        return '删除失败';
    }

    /**
     * @param $utp
     * @return array|ActiveRecord[]
     */
    public function findUsersByType($utp) {
        return self::find()->where(['status'=>self::STATUS_ACTIVE])->andWhere(['type'=>$utp])->all();
    }

    /**
     * 根据用户类型获取用户列表，
     *   admin不用过滤数据
     * @param $utp
     * @return array|ActiveRecord[]
     */
    public function findUsers($utp) {
        $user = Yii::$app->user->identity;
        if ($user->type == self::ADMIN_USER) {
            $query = self::find()->where(['status'=>self::STATUS_ACTIVE, 'type'=>$utp]);
        }else {
            $query = self::find()
                ->where(['status'=>self::STATUS_ACTIVE, 'type'=>$utp])
                ->andWhere([
                    'OR',
                    ['cuid'=>$user->getId()],
                    ['id'=>$user->getId()]
                ]);
        }
        return $query->all();
    }

    /**
     * @param $utp
     * @return array
     */
    public function findUserIdsByType($utp) {
        $users = $this->findUsersByType($utp);
        $ids = [];
        if (count($users) > 0) {
            foreach ($users as $user) {
                $ids[] = $user['id'];
            }
        }
        return $ids;
    }

    public static function haveOpPermission($id, $tp='') {
        $loginUser = Yii::$app->user->identity;
        $user = User::findOne(['id'=>$id, 'status'=>self::STATUS_ACTIVE]);
	    if (!$user)
            return false;

	    if ($user->id == $loginUser->getId()) {
	        return true;
        }

        if ($loginUser->type == User::ADMIN_USER) {
            return true;
        }

        $directlyUnderUserIds = self::getDirectlyUnderUserIds($loginUser->id, 'dwn');
	    if (in_array($user->id, $directlyUnderUserIds)) {
	        return true;
        }

        if ($tp == 'all') {
            $subordinateUserIds = self::getSubordinateUserIds($loginUser->id);
            if (in_array($user->id, $subordinateUserIds)) {
                return true;
            }
        }

        return false;
    }
    // 用户直属下级用户ID【】
    public static function getDirectlyUnderUserIds($uid='', $tp='') {
        if (!$tp) {
            $sql = "SELECT shj_user.* FROM shj_user LEFT JOIN shj_user_relation ur ON shj_user.id=ur.uid WHERE shj_user.cuid = $uid OR ur.puid=$uid";
            $row = Yii::$app->db->createCommand($sql)->queryAll();
        }else {
            $introUserIds = User::getDownUid([User::INTRO_USER, User::SV_USER], $uid);
            $row = self::find()
                ->leftJoin('shj_user_relation ur', 'shj_user.id=ur.uid')
                ->andFilterWhere([
                    'OR',
                    ['shj_user.cuid'=>$uid],
                    ['ur.puid'=>$uid],
                    ['ur.dlid'=>$uid],
                    ['ur.puid'=>$introUserIds]
                ])
                ->all();
        }

        $ids = [];
        foreach ($row as $item) {
            $ids[] = $item['id'];
        }
        return $ids;
    }
    // 用户下级用户ID
    public static function getSubordinateUserIds($uid='') {
        // 查五级
        $sql = "
            SELECT * FROM shj_user_relation WHERE puid IN (
                SELECT uid FROM shj_user_relation WHERE puid IN (
                    SELECT uid FROM shj_user_relation WHERE puid IN (
                        SELECT uid FROM shj_user_relation WHERE puid IN (
                            SELECT uid FROM shj_user_relation WHERE puid=$uid OR uid=$uid
                        ) OR uid=$uid
                    ) OR uid=$uid
                ) OR uid=$uid
            ) OR uid=$uid
        ";
        $row = Yii::$app->db->createCommand($sql)->queryAll();

        $ids = [];
        foreach ($row as $item) {
            $ids[] = $item['uid'];
        }
        return $ids;
    }
    // 用户上级用户ID
    public static function getSuperiorUserIds($uid='') {
        // 查五级
        $sql = "
            SELECT * FROM shj_user_relation WHERE uid IN (
                SELECT puid FROM shj_user_relation WHERE uid IN (
                    SELECT puid FROM shj_user_relation WHERE uid IN (
                        SELECT puid FROM shj_user_relation WHERE uid IN (
                            SELECT puid FROM shj_user_relation WHERE uid=$uid
                        ) OR uid=$uid
                    ) OR uid=$uid
                ) OR uid=$uid
            ) OR uid=$uid
        ";
        $row = Yii::$app->db->createCommand($sql)->queryAll();

        $ids = [];
        foreach ($row as $item) {
            $ids[] = $item['uid'];
        }
        return $ids;
    }
    // 用户所在关系链
    public static function getUserRelationUids($uid='') {
        // 查五级
        $sql = "
            SELECT * FROM shj_user_relation WHERE puid IN (
                SELECT uid FROM shj_user_relation WHERE puid IN (
                    SELECT uid FROM shj_user_relation WHERE puid IN (
                        SELECT uid FROM shj_user_relation WHERE puid IN (
                            SELECT uid FROM shj_user_relation WHERE puid=$uid OR uid=$uid
                        ) OR uid=$uid
                    ) OR uid=$uid
                ) OR uid=$uid
            ) OR uid=$uid
        ";
        $row = Yii::$app->db->createCommand($sql)->queryAll();

        $ids = [];
        foreach ($row as $item) {
            $ids[] = $item['uid'];
        }

        // 查五级
        $sql = "
            SELECT * FROM shj_user_relation WHERE uid IN (
                SELECT puid FROM shj_user_relation WHERE uid IN (
                    SELECT puid FROM shj_user_relation WHERE uid IN (
                        SELECT puid FROM shj_user_relation WHERE uid IN (
                            SELECT puid FROM shj_user_relation WHERE puid=$uid OR uid=$uid
                        ) OR uid=$uid
                    ) OR uid=$uid
                ) OR uid=$uid
            ) OR uid=$uid
        ";
        $row = Yii::$app->db->createCommand($sql)->queryAll();

        foreach ($row as $item) {
            $ids[] = $item['puid'];
        }
        return $ids;
    }

    // 获取上级用户【添加用户用】
    public static function getPUsers($type='', $uid='') {
        $user = Yii::$app->user->identity;
        if (!$type) {
            return [];
        }

        $query = User::find()->where(['status'=>self::STATUS_ACTIVE]);
        if ($uid) {
            $query->andWhere(['<>', 'id', $uid]);
        }
        $query->andWhere(['<>', 'id', $user->getId()]);
        if ($user->type == User::ADMIN_USER) {
            if (in_array($type, [User::DL_USER, User::INTRO_USER, User::SV_USER])) { // 代理，上级用户 admin、代理、介绍人
                $pusers = $query->andWhere(['type'=>[User::ADMIN_USER, User::DL_USER, User::INTRO_USER]])->all();
            }elseif ($type == User::HOTEL_USER) { // 店长, 代理、介绍人、服务商
                $pusers = $query->andWhere(['type'=>[User::DL_USER, User::INTRO_USER, User::SV_USER]])->all();
            }elseif ($type == User::BH_USER) { // 补货员, 代理、店长、服务商
                $pusers = $query->andWhere(['type'=>[User::DL_USER, User::HOTEL_USER, User::SV_USER]])->all();
            }else {
                $pusers = [];
            }
        }elseif ($user->type == User::DL_USER) {
            $query->andWhere([
                'OR',
                ['cuid'=>$user->id],
                ['id'=>UserRelation::find()
                    ->select('uid')
                    ->where([
                        'OR',
                        ['puid'=>$user->id],
                        ['dlid'=>$user->id]
                    ])
                ]
            ]);
            $pusers = [];
            if (in_array($type, [User::DL_USER, User::INTRO_USER, User::SV_USER])) { // 代理: 代理、介绍人、服务商
                $pusers = $query->andWhere(['type'=>[User::INTRO_USER]])->all();
            }elseif ($type == User::HOTEL_USER) { // 店长: 代理、介绍人、服务商
                $pusers = $query->andWhere(['type'=>[User::INTRO_USER, User::SV_USER]])->all();
            }elseif ($type == User::BH_USER) { // 补货员: 代理、店长
                $pusers = $query->andWhere(['type'=>[User::DL_USER, User::HOTEL_USER, User::SV_USER]])->all();
            }
        }else {
            $pusers = [];
        }
        array_unshift($pusers, $user);

        $list = [];
        foreach ($pusers as $puser) {
            $list[] = [
                'id' => $puser['id'],
                'username' => $puser['username'],
                'typeStr' => $puser->typeStr,
            ];
        }

        return $list;
    }
    public static function getPUsers2($type='', $uid='') {
        $user = Yii::$app->user->identity;
        if (!$type) {
            return [];
        }

        $query = User::find()->where(['status'=>self::STATUS_ACTIVE]);
        if ($uid) {
            $query->andWhere(['<>', 'id', $uid]);
        }
        $query->andWhere(['<>', 'id', $user->getId()]);
        if ($user->type != User::ADMIN_USER) {
            $query->andWhere([
                'id'=>UserRelation::find()
                    ->select('uid')
                    ->where([
                        'OR',
                        ['puid'=>$user->id],
                        ['dlid'=>$user->id]
                    ])
            ])->andWhere(['<>', 'type', User::DL_USER]);
        }
        $pusers = [];
        if ($type == [User::DL_USER, User::INTRO_USER, User::SV_USER, User::HOTEL_USER]) {
            $pusers = $query->andWhere(['type'=>[User::ADMIN_USER, User::DL_USER, User::INTRO_USER, User::SV_USER]])->all();
        }elseif ($type == User::DL_USER) { // 代理: admin、介绍人、代理
            $pusers = $query->andWhere(['type'=>[User::ADMIN_USER, User::INTRO_USER, User::DL_USER, User::SV_USER]])->all();
        }elseif ($type == User::INTRO_USER) { // 介绍人: admin、代理、介绍人、服务商
            $pusers = $query->andWhere(['type'=>[User::ADMIN_USER, User::DL_USER, User::INTRO_USER, User::SV_USER]])->all();
        }elseif ($type == User::SV_USER) { // 服务商: 代理、介绍人、服务商
            $pusers = $query->andWhere(['type'=>[User::DL_USER, User::INTRO_USER, User::SV_USER]])->all();
        }elseif ($type == User::HOTEL_USER) { // 店长: 代理、介绍人、服务商
            $pusers = $query->andWhere(['type'=>[User::DL_USER, User::INTRO_USER, User::SV_USER]])->all();
        }elseif ($type == User::BH_USER) { // 补货员: admin、代理、介绍人、服务商、店长
            $pusers = $query->andWhere(['type'=>[User::ADMIN_USER, User::DL_USER, User::INTRO_USER, User::SV_USER, User::HOTEL_USER]])->all();
        }else { // 全部: admin、代理、介绍人、服务商、店长
            $pusers = $query->andWhere(['type'=>[User::ADMIN_USER, User::DL_USER, User::INTRO_USER, User::SV_USER, User::HOTEL_USER]])->all();
        }

        array_unshift($pusers, $user);

        $list = [];
        foreach ($pusers as $puser) {
            $list[] = [
                'id' => $puser['id'],
                'username' => $puser['username'],
                'typeStr' => $puser->typeStr,
            ];
        }

        return $list;
    }

    // 设置用户开启多微信绑定登录
    public function setMoreWxlogin($is_more_wxlogin='') {
        $this->is_more_wxlogin = $is_more_wxlogin;

        return $this->save();
    }

    // 设置用户分成类别
    public function setUserPropType($prop_type=0) {
        $this->prop_type = $prop_type;

        return $this->save();
    }

    // 用户登录绑定微信信息 / 用户补货通知绑定微信信息
    // 绑定登录的微信信息
    public function getLoginWxuser() {
        return $this->hasOne(WechatUser::className(), ['openid'=>'pub_openid']);
    }
    // 绑定登录的微信信息
    public function getLoginWxusers() {
        $wxUsers = [];
        $userBindWecharInfo = $this->loginWxuser;
        if ($userBindWecharInfo) {
            $wxUsers[] = $userBindWecharInfo;
        }

        $query = WechatUser::find()->where(['login_uid'=>$this->id])->distinct('openid');
        if ($userBindWecharInfo) {
            $query->andWhere(['<>', 'openid', $userBindWecharInfo->openid]);
        }
        $row = $query->all();

        foreach ($row as $item) {
            $wxUsers[] = $item;
        }

        return $wxUsers;
    }
    // 绑定补货通知的微信信息
    public function getCpfrNotifyWxuser() {
        return $this->hasOne(WechatUser::className(), ['openid'=>'pub_openid']);
    }
    // 绑定补货通知的微信信息
    public function getCpfrNotifyWxusers() {
        $wxUsers = [];
        $userBindWecharInfo = $this->cpfrNotifyWxuser;
        if ($userBindWecharInfo) {
            $wxUsers[] = $userBindWecharInfo;
        }

        $query = WechatUser::find()->where(['cpfr_uid'=>$this->id])->distinct('openid');
        if ($userBindWecharInfo) {
            $query->andWhere(['<>', 'openid', $userBindWecharInfo->openid]);
        }
        $row = $query->all();

        foreach ($row as $item) {
            $wxUsers[] = $item;
        }

        return $wxUsers;
    }
    // 绑定补货的信息
    public function getCpfrWxuser() {
        return $this->hasOne(WechatUser::className(), ['openid'=>'openid']);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        // throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
        if (!$token) {
            return null;
        }

        // TODO 增加缓存
        $_user = self::find()
            ->where(['access_token'=>$token])
            // ->andWhere(['>', 'expirer', time()])
            ->one();
        if (!$_user) {
            return null;
        }

        return $_user;
    }

    // 获取Access-token
    public function generateAccessToken () {
        $this->access_token = Yii::$app->security->generateRandomString();

        return $this->access_token;
    }

    // 重置AccessToken过期时间
    public function resetAccessTokenExpirer() {
        $this->expirer = time() + Yii::$app->params['auth.access.token'];
        if ($this->save()) {
            return true;
        }

        return false;
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByPhone($phone)
    {
        return static::findOne(['phone' => $phone, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param $password
     * @throws \yii\base\Exception
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

    // TODO 暂时分成比例验证器，在创建过程中无法在此调用到直属父级,所以暂时当做普通函数使用【不做为自动校验器】
    // public function verifPropRules($attribute, $params) {
    public function verifPropRules($puid) {
        $attribute = 'prop';
        if ($this->prop < 0) {
            $this->addError($attribute, "分成比不能小于0.");
            throw (new \Exception("分成比不能小于0."));
        }
        if ($this->prop > 100) {
            $this->addError($attribute, "分成比不能大于100.");
            throw (new \Exception("分成比不能大于100."));
        }


        $puser = User::findOne($puid);
        if ($puser && !$puser->prop_type && $this->prop > $puser->prop) {
            $this->addError($attribute, "分成比不能大于父级分成比".$puser->prop."%.");
            throw (new \Exception("分成比不能大于父级分成比".$puser->prop."%."));
        }
        if ($puser && $puser->prop_type) {
            $dlId = User::getUserPDlId($puid);
            $pu = User::findOne($dlId);
            if ($pu && !$pu->prop_type && $this->prop > $pu->prop) {
                $this->addError($attribute, "分成比不能大于父级分成比".$pu->prop."%.");
                throw (new \Exception("分成比不能大于父级分成比".$pu->prop."%."));
            }
        }

    }
    private function verifPropRules2($checkPropData) {
        $attribute = 'prop';
        if ($this->prop < 0) {
            $this->addError($attribute, "分成比不能小于0.");
            throw (new \Exception("分成比不能小于0."));
        }
        if ($this->prop > 100) {
            $this->addError($attribute, "分成比不能大于100.");
            throw (new \Exception("分成比不能大于100."));
        }
        if ($this->prop < $checkPropData['minProp']) {
            $this->addError($attribute, '购物分成比不能小于' . $checkPropData['minProp'] . '%');
            throw (new \Exception('购物分成比不能小于' . $checkPropData['minProp'] . '%'));
        }
        if ($this->prop > $checkPropData['maxProp']) {
            $this->addError($attribute, '购物分成比不能大于' . $checkPropData['maxProp'] . '%');
            throw (new \Exception('购物分成比不能大于' . $checkPropData['maxProp'] . '%'));
        }

        if ($this->charge_prop < 0) {
            $this->addError($attribute, "分成比不能小于0.");
            throw (new \Exception("充电分成比不能小于0."));
        }
        if ($this->charge_prop > 100) {
            $this->addError($attribute, "分成比不能大于100.");
            throw (new \Exception("充电分成比不能大于100."));
        }
        if ($this->charge_prop < $checkPropData['minChargeProp']) {
            $this->addError($attribute, '充电分成比不能小于' . $checkPropData['minChargeProp'] . '%');
            throw (new \Exception('充电分成比不能小于' . $checkPropData['minChargeProp'] . '%'));
        }
        if ($this->charge_prop > $checkPropData['maxChargeProp']) {
            $this->addError($attribute, '充电分成比不能大于' . $checkPropData['maxChargeProp'] . '%');
            throw (new \Exception('充电分成比不能大于' . $checkPropData['maxChargeProp'] . '%'));
        }
    }
}
