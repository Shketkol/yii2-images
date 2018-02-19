<?php

namespace shketkol\images\src\models;


use yii\db\ActiveRecord;
use Yii;
use yii\db\Expression;

class Position
{

    public static function insertPosition(ActiveRecord &$model)
    {

        if (method_exists($model, 'getPositionAttributes')) {
            if (empty($model->position)) {

                $conditions = [];
                $addAttr = $model->getPositionAttributes();
                foreach ($addAttr as $key => $val) {
                    if (!is_string($key)) {
                        $key = $val;
                        $val = $model->{$val};
                    }
                    $conditions[$key] = $val;
                }

                $modelClass = $model::className();
                $result = $modelClass::find()
                    ->where($conditions)
                    ->orderBy('position DESC')
                    ->one();
                if (empty($result)){
                    $model->position = 1;
                } else {
                    $model->position = $result->position + 1;
                }
            }
        }
    }

    public static function updatePosition(ActiveRecord &$model)
    {
        if (method_exists($model, 'getPositionAttributes')) {
            if (isset($model->status) && $model->status == 0) {
                self::removePosition($model);
            } else {
                $position = $model->position;
                $plId = $model->getPrimaryKey();
                $oldModel = $model::findOne(['id' => $plId]);
                $oldPosition = $oldModel->position;

                if ($oldPosition != $position) {
                    if ($position > $oldPosition) {
                        $columns = array('position' => new Expression('position - 1'));
                        $conditions = 'position<=:position AND position >= :oldPosition';
                        $params = array(':position' => $position, ':oldPosition' => $oldPosition);

                    } else {
                        $columns = array('position' => new Expression('position + 1'));
                        $conditions = 'position>=:position AND position <= :oldPosition';
                        $params = array(':position' => $position, ':oldPosition' => $oldPosition);
                    }

                    $addAttr = $model->getPositionAttributes();

                    if (empty($addAttr)) {
                        $addAttr = array();
                    }

                    foreach ($addAttr as $key => $val) {
                        if (!is_string($key)) {
                            $key = $val;
                            $val = $model->{$val};
                        }

                        $conditions .= ' AND ' . $key . ' = :' . $key;
                        $params[':' . $key] = $val;
                    }
//                $conditions.= ' AND position != 1';
//                var_dump($model->tableName(), $columns, $conditions, $params);
                    Yii::$app->db->createCommand()->update($model->tableName(), $columns, $conditions, $params)->execute();
                }
            }

        }
    }

    public static function removePosition(ActiveRecord &$model)
    {
        if (method_exists($model, 'getPositionAttributes')) {
            $position = $model->position;
            $addAttr = $model->getPositionAttributes();

            $columns = array('position' => new Expression('position - 1'));
            $conditions = 'position>=:position';
            $params = array(':position' => $position);
            if (empty($addAttr)) {
                $addAttr = array();
            }

            foreach ($addAttr as $key => $val) {
                if (!is_string($key)) {
                    $key = $val;
                    $val = $model->{$val};
                }

                $conditions .= ' AND ' . $key . ' = :' . $key;
                $params[':' . $key] = $val;
            }

            Yii::$app->db->createCommand()->update($model->tableName(), $columns, $conditions, $params)->execute();
        }
    }


}