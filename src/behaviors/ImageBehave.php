<?php
/**
 * Created by PhpStorm.
 * User: kostanevazno
 * Date: 22.06.14
 * Time: 16:58
 */

namespace shketkol\images\src\behaviors;


use shketkol\images\src\models\Image;
use shketkol\images\src\ModuleTrait;
use yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\BaseFileHelper;

class ImageBehave extends Behavior
{
    use ModuleTrait;
    public $createAliasMethod = false;


    public function getPreviews($name)
    {
        $initialPreview = [];
        if (!$this->owner->isNewRecord) {
            $images = $this->getImagesByName($name);
            if (is_array($images)) {
                foreach ($images as $value) {
                    $initialPreview[] = $value->getUrl('120x120');
                }
            }
        } else {
            $images = $this->getImagesByNameAttach($name);
            if (is_array($images)) {
                foreach ($images as $value) {
                    $initialPreview[] = $value->getUrlAttach();
                }
            }
        }

        return $initialPreview;
    }

    public function getPreviewsConfig($name)
    {
        $id = [];
        if (!$this->owner->isNewRecord) {
            $images = $this->getImagesByName($name);
            if (is_array($images)) {
                foreach ($images as $value) {
                    $id[] = array(
                        'url' => '/yii2images/upload/delete',
                        'key' => $value['id'],
                        'extra' => array(
                            'id' => $value['itemId'],
                            'model' => $this->owner::className()
                        )
                    );
                }
            }
        } else {
            $images = $this->getImagesByNameAttach($name);
            if (is_array($images)) {
                foreach ($images as $value) {
                    $id[] = array(
                        'url' => '/yii2images/upload/delete',
                        'key' => $value['id'],
                        'extra' => array(
                            'id' => $value['itemId'],
                            'model' => $this->owner::className()
                        )
                    );
                }
            }
        }

        return $id;
    }

    public function getImageById($id){
        if ($this->getModule()->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = $this->getModule()->className;
            $imageQuery = $class::find();
        }
        $finder = $this->getImagesFinder(['id' => $id]);
        $imageQuery->where($finder);

        $img = $imageQuery->one();
        if(!$img){
            return $this->getModule()->getPlaceHolder();
        }

        return $img;
    }

    public function getImagesByNameAttach($name = '')
    {
        if ($this->getModule()->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = $this->getModule()->className;
            $imageQuery = $class::find();
        }
        $imageQuery->where([
            'modelName' => $this->getModule()->getShortClass($this->owner),
            'user_id' => (!$this->getModule()->userId) ? Yii::$app->user->id : $this->getModule()->userId,
            'name' => $name
        ]);
        $imageQuery->orderBy(['position' => SORT_ASC]);

        $img = $imageQuery->all();
        if(!$img){
            return $this->getModule()->getPlaceHolder();
        }
        return $img;
    }

    /**
     * @var ActiveRecord|null Model class, which will be used for storing image data in db, if not set default class(models/Image) will be used
     */

    /**
     *
     * Method copies image file to module store and creates db record.
     *
     * @param $absolutePath
     * @param bool $isMain
     * @return bool|Image
     * @throws \Exception
     */
    public function attachImage($absolutePath, $isMain = false, $name = '')
    {
        if(!preg_match('#http#', $absolutePath)){
            if (!file_exists($absolutePath)) {
                throw new \Exception('File not exist! :'.$absolutePath);
            }
        }else{
            //nothing
        }

        $pictureFileName =
            substr(md5(microtime(true) . $absolutePath), 4, 6)
            . '.' .
            pathinfo($absolutePath, PATHINFO_EXTENSION);
        $pictureSubDir = $this->getModule()->getModelSubDir($this->owner);
        $storePath = $this->getModule()->getStorePath($this->owner);

        $newAbsolutePath = $storePath .
            DIRECTORY_SEPARATOR . $pictureSubDir .
            DIRECTORY_SEPARATOR . $pictureFileName;

        BaseFileHelper::createDirectory($storePath . DIRECTORY_SEPARATOR . $pictureSubDir,
            0777, true);

        copy($absolutePath, $newAbsolutePath);

        if (!file_exists($newAbsolutePath)) {
            throw new \Exception('Cant copy file! ' . $absolutePath . ' to ' . $newAbsolutePath);
        }

        if ($this->getModule()->className === null) {
            $image = new Image;
        } else {
            $class = $this->getModule()->className;
            $image = new $class();
        }
        $image->itemId = (!empty($this->owner->primaryKey)) ? $this->owner->primaryKey : null;
        $image->filePath = $pictureSubDir . '/' . $pictureFileName;
        $image->modelName = $this->getModule()->getShortClass($this->owner);
        $image->name = $name;
        $image->user_id = (!empty($this->owner->primaryKey)) ? null : (!$this->getModule()->userId) ? Yii::$app->user->id : $this->getModule()->userId;

        $image->urlAlias = $this->getAlias($image);

        if(!$image->save()){
            return false;
        }

        if (count($image->getErrors()) > 0) {

            $ar = array_shift($image->getErrors());

            unlink($newAbsolutePath);
            throw new \Exception(array_shift($ar));
        }
        $img = $this->owner->getImage();

        //If main image not exists
        if(
            is_object($img)
            or
            $img == null
            or
            $isMain
        ){
            $this->setMainImage($image);
        }


        return $image;
    }

    /**
     * Sets main image of model
     * @param $img
     * @throws \Exception
     */
    public function setMainImage($img)
    {
        if ($this->owner->primaryKey != $img->itemId) {
            throw new \Exception('Image must belong to this model');
        }
        $counter = 1;
        /* @var $img Image */
        $img->setMain(true);
        $img->urlAlias = $this->getAliasString() . '-' . $counter;
        $img->save();


        $images = $this->owner->getImages();
        foreach ($images as $allImg) {

            if ($allImg->getPrimaryKey() == $img->getPrimaryKey()) {
                continue;
            } else {
                $counter++;
            }

            $allImg->setMain(false);
            $allImg->urlAlias = $this->getAliasString() . '-' . $counter;
            $allImg->save();
        }

        $this->owner->clearImagesCache();
    }

    /**
     * Clear all images cache (and resized copies)
     * @return bool
     */
    public function clearImagesCache()
    {
        $cachePath = $this->getModule()->getCachePath();
        $subdir = $this->getModule()->getModelSubDir($this->owner);

        $dirToRemove = $cachePath . '/' . $subdir;

        if (preg_match('/' . preg_quote($cachePath, '/') . '/', $dirToRemove)) {
            BaseFileHelper::removeDirectory($dirToRemove);
            //exec('rm -rf ' . $dirToRemove);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns model images
     * First image alwats must be main image
     * @return array|yii\db\ActiveRecord[]
     */
    public function getImages()
    {
        $finder = $this->getImagesFinder();

        if ($this->getModule()->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = $this->getModule()->className;
            $imageQuery = $class::find();
        }
        $imageQuery->where($finder);
        $imageQuery->orderBy(['isMain' => SORT_DESC, 'id' => SORT_ASC]);

        $imageRecords = $imageQuery->all();
        if(!$imageRecords && $this->getModule()->placeHolderPath){
            return [$this->getModule()->getPlaceHolder()];
        }
        return $imageRecords;
    }

    /**
     * returns model images by name
     * @return array|null|ActiveRecord
     */
    public function getImagesByName($name = '')
    {
        if ($this->getModule()->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = $this->getModule()->className;
            $imageQuery = $class::find();
        }
        $finder = $this->getImagesFinder(['name' => $name]);
        $imageQuery->where($finder);
        $imageQuery->orderBy(['position' => SORT_ASC]);

        $img = $imageQuery->all();

        return $img;
    }

    /**
     * returns main model image
     * @return array|null|ActiveRecord
     */
    public function getImage()
    {
        if ($this->getModule()->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = $this->getModule()->className;
            $imageQuery = $class::find();
        }
        $finder = $this->getImagesFinder(['isMain' => 1]);
        $imageQuery->where($finder);
        $imageQuery->orderBy(['isMain' => SORT_DESC, 'id' => SORT_ASC]);

        $img = $imageQuery->one();
        if(!$img){
            return $this->getModule()->getPlaceHolder();
        }

        return $img;
    }

    /**
     * returns model image by name
     * @return array|null|ActiveRecord
     */
    public function getImageByName($name)
    {
        if ($this->getModule()->className === null) {
            $imageQuery = Image::find();
        } else {
            $class = $this->getModule()->className;
            $imageQuery = $class::find();
        }
        $finder = $this->getImagesFinder(['name' => $name]);
        $imageQuery->where($finder);
        $imageQuery->orderBy(['isMain' => SORT_DESC, 'id' => SORT_ASC]);

        $img = $imageQuery->one();
        if(!$img){
            return $this->getModule()->getPlaceHolder();
        }

        return $img;
    }

    /**
     * Remove all model images
     */
    public function removeImages()
    {
        $images = $this->owner->getImages();
        if (count($images) < 1) {
            return true;
        } else {
            foreach ($images as $image) {
                $this->owner->removeImage($image);
            }
            $storePath = $this->getModule()->getStorePath($this->owner);
            $pictureSubDir = $this->getModule()->getModelSubDir($this->owner);
            $dirToRemove = $storePath . DIRECTORY_SEPARATOR . $pictureSubDir;
            BaseFileHelper::removeDirectory($dirToRemove);
        }

    }

    /**
     * removes concrete model's image
     * @param Image $img
     * @throws \Exception
     * @return bool
     */
    public function removeImage(Image $img)
    {
//        if ($img instanceof models\PlaceHolder) {
//            return false;
//        }
        $img->clearCache();

        $storePath = $this->getModule()->getStorePath();

        $fileToRemove = $storePath . DIRECTORY_SEPARATOR . $img->filePath;
        if (preg_match('@\.@', $fileToRemove) and is_file($fileToRemove)) {
            unlink($fileToRemove);
        }
        $img->delete();
        return true;
    }

    private function getImagesFinder($additionWhere = false)
    {
        $base = [
            'itemId' => $this->owner->primaryKey,
            'modelName' => $this->getModule()->getShortClass($this->owner)
        ];

        if ($additionWhere) {
            $base = \yii\helpers\BaseArrayHelper::merge($base, $additionWhere);
        }

        return $base;
    }

    /** Make string part of image's url
     * @return string
     * @throws \Exception
     */
    private function getAliasString()
    {
        if ($this->createAliasMethod) {
            $string = $this->owner->{$this->createAliasMethod}();
            if (!is_string($string)) {
                throw new \Exception("Image's url must be string!");
            } else {
                return $string;
            }

        } else {
            return substr(md5(microtime()), 0, 10);
        }
    }

    /**
     *
     * Обновить алиасы для картинок
     * Зачистить кэш
     */
    private function getAlias()
    {
        $aliasWords = $this->getAliasString();
        $imagesCount = count($this->owner->getImages());

        return $aliasWords . '-' . intval($imagesCount + 1);
    }

    public function setImages($name, $userId = null)
    {
        if (is_null($userId)){
            $userId = Yii::$app->user->id;
        }
        $models = Image::find()
            ->where(['name' => $name, 'users_id' => $userId])
            ->all();
        if (!empty($models)){
            foreach ($models as $model){
                $path = $model->getPathToOrigin();
                if ($this->attachImage($path, false, $name)){
                    $model->delete();
                };
            }
        }
    }
}
