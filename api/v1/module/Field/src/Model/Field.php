<?php

namespace Field\Model;

use Oxzion\Model\Model;

class Field extends Model{
	public $id;
	public $name;
	public $columnname;
	public $text;
	public $helpertext;
	public $type;
	public $options;
	public $color;
	public $regexpvalidator;
	public $validationtext;
	public $specialvalidator;
	public $expression;
	public $condition;
	public $premiumname;
	public $xflat_parameter;
	public $esign_parameter;
	public $field_type;
	public $category;

	public function exchangeArray($data){
		$this->id = !empty($data['id']) ? $data['id'] : 0;
		$this->name = !empty($data['name']) ? $data['name'] : null;
		$this->columnname = !empty($data['columnname']) ? $data['columnname'] : null;
		$this->text = !empty($data['text']) ? $data['text'] : null;
		$this->helpertext = !empty($data['helpertext']) ? $data['helpertext'] : null;
		$this->type = !empty($data['type']) ? $data['type'] : null;
		$this->options = !empty($data['options']) ? $data['options'] : null;
		$this->color = !empty($data['color']) ? $data['color'] : null;
		$this->disablejavascript = !empty($data['disablejavascript']) ? $data['disablejavascript'] : null;
		$this->regexpvalidator = !empty($data['regexpvalidator']) ? $data['regexpvalidator'] : null;
		$this->validationtext = !empty($data['validationtext']) ? $data['validationtext'] : null;
		$this->specialvalidator = !empty($data['specialvalidator']) ? $data['specialvalidator'] : null;
		$this->expression = !empty($data['expression']) ? $data['expression'] : null;
		$this->condition = !empty($data['condition']) ? $data['condition'] : null;
		$this->premiumname = !empty($data['premiumname']) ? $data['premiumname'] : null;
		$this->xflat_parameter = !empty($data['xflat_parameter']) ? $data['xflat_parameter'] : null;
		$this->esign_parameter = !empty($data['esign_parameter']) ? $data['esign_parameter'] : null;
		$this->field_type = !empty($data['field_type']) ? $data['field_type'] : null;
		$this->category = !empty($data['category']) ? $data['category'] : null;


	}

	public function toArray(){
		$data = array();
		$data['id'] = $this->id;
		$data['name'] = $this->name;
		$data['columnname'] = $this->columnname;
		$data['text'] = $this->text;
		$data['helpertext'] = $this->helpertext;
		$data['type'] = $this->type;
		$data['options'] = $this->options;
		$data['color'] = $this->color;
		$data['regexpvalidator'] = $this->regexpvalidator;
		$data['validationtext'] = $this->validationtext;
		$data['specialvalidator'] = $this->specialvalidator;
		$data['expression'] = $this->expression;
		$data['condition'] = $this->condition;
		$data['premiumname'] = $this->premiumname;
		$data['xflat_parameter'] = $this->xflat_parameter;
		$data['esign_parameter'] = $this->esign_parameter;
		$data['field_type'] = $this->field_type;
		$data['category'] = $this->category;

		return $data;
	}
}