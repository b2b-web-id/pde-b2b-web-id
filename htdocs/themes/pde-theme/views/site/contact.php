<?php
/* @var $this SiteController */
/* @var $model ContactForm */
/* @var $form TbActiveForm */

$this->pageTitle=Yii::app()->name . ' - Contact Us';
$this->breadcrumbs=array(
	'Contact',
);
?>

<?php
$this->beginWidget(
	'bootstrap.widgets.TbHeroUnit',array(
		'heading'=>CHtml::encode("Contact Us"),
		)
	);
?>
<p><a href='http://www.b2b.web.id'>B2B.Web.ID</a>.</p>
<?php
$this->endWidget();
?><h1></h1>
