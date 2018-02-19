<?php
namespace shketkol\images\src\controllers;

use shketkol\images\src\models\Image;
use shketkol\images\src\ModuleTrait;
use Yii;
use yii\helpers\BaseFileHelper;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\UploadedFile;

class UploadController extends Controller
{
    use ModuleTrait;

    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;

        return parent:: beforeAction($action);
    }

    public function actionUpload()
    {
        if (Yii::$app->request->isAjax) {

            $model_name = $_POST['model'];
            if (!empty($_POST['id'])) {
                $model = $model_name::findOne($_POST['id']);
            } else {
                $model = new $model_name();
            }

            $image = UploadedFile::getInstances($model, $_POST['field']);

            $count = [];
            $issetImages = $model->getImagesByName($_POST['name']);
            if (!empty($issetImages)){
                foreach ($issetImages as $value) {
                    if (!empty($value->id)) {
                        $count[] = $value->id;
                    }
                }
            }


            if (count($count) < (int)$_POST['max']) {
                if (!empty($image)) {
                    if (is_array($image)) {
                        BaseFileHelper::createDirectory($this->getModule()->imagesTempPath);

                        $path = $this->getModule()->imagesTempPath.'/temp_'.time().$image[0]->name;
                        move_uploaded_file($image[0]->tempName, $path);
                        $result = $model->attachImage($path, false, $_POST['name']);
                        unlink($path);
                    }
                    return Json::encode([
                        'initialPreview' => [
                            '/uploads/store/'.$result->filePath
                        ],
                        'initialPreviewAsData' => true,
                        'initialPreviewConfig' => [
                            [
                                'url' => '/yii2images/upload/delete',
                                'key' => $result->id,
                                'extra' => array(
                                    'id' => ($model->isNewRecord) ? 0 : $model->id,
                                    'model' => $model_name
                                )
                            ]
                        ]
                    ]);
                }
            } else {
                return Json::encode(array('error' => 'Уже загруженно максимальное количество файлов'));
            }
        }
        return Json::encode('');
    }

    public function actionDelete()
    {
        if (Yii::$app->request->isAjax) {
            $model_name = $_POST['model'];
            if ($_POST['id'] == 0){
                $image = Image::findOne(['id' => $_POST['key']]);
                if (!empty($image)) {
                    $model = new $model_name();
                    $model->removeImage($image);
                    return Json::encode('');
                }
            } else {
                $model = $model_name::findOne($_POST['id']);

                if (!empty($model)) {
                    $image = $model->getImageById($_POST['key']);
                    if (!empty($image)) {
                        $model->removeImage($image);
                        $this->removeDirectory($image);
                        return Json::encode('');
                    }
                }
            }

        }
    }

    public function actionChangePosition()
    {
        if (Yii::$app->request->isAjax) {
            $model_name = Image::className();
            $model = $model_name::findOne($_GET['id']);

            if (!empty($model)) {
                $model->position = $_GET['position'];
                $model->save();
            }
        }
    }

    protected function removeDirectory($image)
    {
        rmdir($this->getModule()->imagesStorePath .'/'. $image->modelName . 's/' . $image->modelName.$image->itemId);
    }
}