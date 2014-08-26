<?php
Yii::setPathOfAlias('bootstrap', dirname(__FILE__).'/../extensions/bootstrap');
return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'PDE',
	'preload'=>array('log'),
	'theme'=>'pde-theme',
	'import'=>array(
		'application.models.*',
		'application.components.*',
		'application.modules.user.models.*',
	),
	'modules'=>array(
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'pde',
			'ipFilters'=>array('127.0.0.1','::1'),
			'generatorPaths'=>array('bootstrap.gii'),
		),
		'user' => array(
			'debug' => false,
			'userTable' => 'user',
			'translationTable' => 'translation',
		),
		'usergroup' => array(
			'usergroupTable' => 'usergroup',
			'usergroupMessageTable' => 'user_group_message',
		),
		'membership' => array(
			'membershipTable' => 'membership',
			'paymentTable' => 'payment',
		),
		'friendship' => array(
			'friendshipTable' => 'friendship',
		),
		'profile' => array(
			'privacySettingTable' => 'privacysetting',
			'profileFieldTable' => 'profile_field',
			'profileTable' => 'profile',
			'profileCommentTable' => 'profile_comment',
			'profileVisitTable' => 'profile_visit',
		),
		'role' => array(
			'roleTable' => 'role',
			'userRoleTable' => 'user_role',
			'actionTable' => 'action',
			'permissionTable' => 'permission',
		),
		'message' => array(
			'messageTable' => 'message',
		),
	),
	'components'=>array(
		'user'=>array(
			'class'=>'application.modules.user.components.YumWebUser',
			'allowAutoLogin'=>true,
			'loginUrl'=>array('user/user/login'),
		),
		'bootstrap'=>array(
			'class'=>'bootstrap.components.Bootstrap',
		),
		'urlManager'=>array(
			'urlFormat'=>'path',
			'rules'=>array(
				'<controller:\w+>/<id:\d+>'=>'<controller>/view',
				'<controller:\w+>/<action:\w+>/<id:\d+>'=>'<controller>/<action>',
				'<controller:\w+>/<action:\w+>'=>'<controller>/<action>',
			),
		),
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=pde',
			'emulatePrepare' => true,
			'username' => 'pde',
			'password' => 'pde',
			'charset' => 'utf8',
		),
		'errorHandler'=>array(
			'errorAction'=>'site/error',
		),
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
				//array(
				//	'class'=>'CWebLogRoute',
				//),
			),
		),
	),
	'params'=>array(
		'adminEmail'=>'pengelola.b2b@gmail.com',
		'name'=>'Pusat Data Elektronik',
	),
);
