<?php
$this->breadcrumbs=array(
	'Users'=>array('index'),
	$model->name=>array('view','id'=>$model->idUser),
	'Update',
);

$this->menu=array(
	//array('label'=>'List User','url'=>array('index')),
	array('label'=>'Create User','url'=>array('create')),
	array('label'=>'View User','url'=>array('view','id'=>$model->idUser)),
	//array('label'=>'Manage User','url'=>array('admin')),
);
?>

<h1>Update User <?php echo $model->idUser; ?></h1>

<?php echo $this->renderPartial('_form',array('model'=>$model)); ?>