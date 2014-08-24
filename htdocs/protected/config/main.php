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
		'ext.eoauth.*',
		'ext.eoauth.lib.*',
	),
	'modules'=>array(
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'pde',
			'ipFilters'=>array('127.0.0.1','::1'),
			'generatorPaths'=>array('bootstrap.gii'),
		),
	),
	'components'=>array(
		'user'=>array(
			'allowAutoLogin'=>true,
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
		'adminEmail'=>'webmaster@example.com',
		'name'=>'Pusat Data Elektronik',
	),
);
