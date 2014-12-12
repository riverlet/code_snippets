<?php
/**
 * Created by PhpStorm.
 * User: mengfanbin
 * Date: 14-12-12
 * Time: 下午12:43
 */

class ActiveRecord extends CActiveRecord {

    /**
     * get a one-line error summary.
     * @return string
     */
    public function getErrorSummary() {
        $errorsText = array();
        foreach($this->getErrors() as $key=>$errors) {
            $errorsText[] = $key.': '.implode(', ', $errors);
        }
        return implode('; ', $errorsText);
    }
} 