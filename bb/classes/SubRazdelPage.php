<?php

namespace bb\classes;

class SubRazdelPage
{
    /**
     * @var Razdel[]
     */
    private $_razdels;

    /**
     * @var SubRazdel[]
     */
    private $_sub_razdels;

    /**
     * @var Category[]
     */
    private $_categories;

    private $lang;

    /**
     * @param $form_name
     * @param $checked_ids
     * @return string
     */
    public function getRazdelCheckBoxes($form_name, $checked_ids=[], $mainRazdel_id=0){
        $rez='';
        foreach ($this->getRazdels() as $r) {
            $rez .= '
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="razdel[]" value="'.$r->getIdRazdel().'" id="check_razd_'.$r->getIdRazdel().'" form="'.$form_name.'" '.(in_array($r->getIdRazdel(), $checked_ids) ? 'checked' : '').' '.($r->getIdRazdel() == $mainRazdel_id ? 'disabled' : '').'>
              <label class="form-check-label" for="check_razd_'.$r->getIdRazdel().'">
                '.$r->getNameRazdelText().'
              </label>
            </div>
            ';
        }
        return $rez;
    }

    /**
     * @param $form_name
     * @param $subRazdelId
     * @param $mainRazdelId
     * @return string
     */
    public function getMainRazdelSelect($form_name, $subRazdelId, $mainRazdelId) {
        $rez= '<select class="form-control-sm edit-field" name="main_razdel_id" form="'.$form_name.'_'.$subRazdelId.'">
                    <option value="0">Выбрать основной раздел</option>';
                    foreach ($this->getAllRazdels() as $r){
                        $rez.='<option value="'.$r->getIdRazdel().'" '.($mainRazdelId==$r->getIdRazdel() ? 'selected' : '').'>'.$r->getNameRazdelText().'</option>';
                    }
        $rez.='</select>';

        return $rez;
    }

    /**
     * @param $checked_ids
     * @return string
     */
    public function getRazdelsString($checked_ids=[], $mainRazdelId=0){
        $rez='';
        foreach ($this->getRazdels() as $r) {
            if (in_array($r->getIdRazdel(), $checked_ids)) {
                if ($r->getIdRazdel() != $mainRazdelId) $rez .= $r->getNameRazdelText().', ';
                else $rez .= '<strong>'.$r->getNameRazdelText().'</strong>, ';
            }
        }
        if ($rez != '') $rez = mb_substr($rez, 0, -2);
        else $rez = 'Разделы не выбраны';

        return '<span class="row-text show">'.$rez.'</span>';
    }

    /**
     * @param $form_name
     * @param $checked_ids
     * @return string
     */
    public function getCatCheckBoxes($form_name, $checked_ids=[]){
        $rez='';
        foreach ($this->getCats() as $c) {
            $rez .= '
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="category[]" value="'.$c->getId().'" form="'.$form_name.'" id="check_cat_'.$c->getId().'" form="'.$form_name.'" '.(in_array($c->getId(), $checked_ids) ? 'checked' : '').'>
              <label class="form-check-label" for="check_cat_'.$c->getId().'">
                '.$c->getName().'
              </label>
            </div>
            ';
        }
        return $rez;
    }

    /**
     * @param $checked_ids
     * @return string
     */
    public function getCategoriesString($checked_ids=[]){
        $rez='';
        foreach ($this->getCats() as $r) {
            if (in_array($r->getId(), $checked_ids)) $rez .= $r->getName().', ';
        }
        if ($rez != '') $rez = mb_substr($rez, 0, -2);
        else $rez = 'Категории не выбраны';

        return '<span class="row-text show-inline">'.$rez.'</span>';
    }

    /**
     * @return Razdel[]|false|void
     */
    private function getRazdels(){
        return $this->_razdels;
    }

    /**
     * @return SubRazdel[]|false|void
     */
    public function getSubRazdelsAll(){
        return $this->_sub_razdels;
    }

    /**
     * @return Category[]
     */
    private function getCats(){
        return $this->_categories;
    }

    /**
     * @return Razdel[]|false|void
     */
    public function getAllRazdels(){
        return $this->_razdels;
    }

    public function __construct($lang='', $razdelIdForFilter=0, $subRazdelIdForFilter=0)
    {
      if ($lang=='') $lang='ru';

        $this->_razdels = Razdel::getAll();
        $this->_sub_razdels = SubRazdel::getAll($lang, $razdelIdForFilter, $subRazdelIdForFilter);
        $this->_categories = Category::getAllCategories();

        $this->lang = $lang;
    }
}
