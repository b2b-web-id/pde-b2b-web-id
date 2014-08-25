<?php
$this->pageTitle=Yii::app()->name.": ".Yii::app()->params['name'];
?>

<?php $this->beginWidget('bootstrap.widgets.TbHeroUnit',array(
    'heading'=>CHtml::encode(Yii::app()->name.": ".Yii::app()->params['name']),
)); ?>
<p>Selamat datang di <?php echo CHtml::encode(Yii::app()->name." (".Yii::app()->params['name'].") "); ?>
yang dibangun dan dikembangkan oleh <a href='http://www.b2b.web.id'>B2B.Web.ID</a>.</p>
<?php $this->endWidget(); ?>

<p>Untuk melihat dan menggunakan data yang ada, Anda harus memiliki akun Google. Managemen pengguna kami terhubung dengan akun Google.</p>
<p>Silakan <?php echo CHtml::link('login',array('site/login')); ?> dengan akun Google Anda.</p>

