<?php

/**
 * @author G.Azamat <m@fx4.ru>
 *
 * This class extends {@link CActiveRecord} and serve for search of values of "virtual attributes"
 * (for example, in {@link CGridView})
 *
 * <b>INSTALLATION, TYPICAL USE CASE</b>
 *
 * If you wish add new virtual attribute (for example "someFunc") to your model, and enable search by this attribute:
 * <ol>
 *      <li> Add to DB-schema new field "_someFunc" (note, that name has prefix "_") </li>
 *      <li> Copy this class in "component" folder. </li>
 *      <li> Extend your model (for example "TestModel") from this class:
 *          <code>
 *              class TestModel extends ActiveRecordVirtualAttribute {
 *                  ...
 *              }
 *          </code>
 *      </li>
 *      <li> Add to TestModel method "virtualAttributes", which returns list of virtual attributes
 *          <code>
 *              class TestModel extends ActiveRecordVirtualAttribute {
 *                  ...
 *                  function virtualAttributes() {
 *                      return array(
 *                          'someFunc'
 *                      );
 *                  }
 *                  ...
 *              }
 *          </code>
 *      </li>
 *      <li> Add protected method "virtualSomeFunc" to class TestModel, which calculates value of virtual attribute
 *          (note, that name has prefix "virtual").<br/>
 *          <b>IMPORTANT:</b> method must use only attributes of TestModel (or constants). If method cannot calculate
 *          value, it must return null.
 *          <code>
 *              class TestModel extends ActiveRecordVirtualAttribute {
 *                  ...
 *                  protected function virtualSomeFunc() {
 *                      if(...){
 *                          return ...;
 *                      }else{
 *                          return ...;
 *                      }
 *                      return null; // if cannot calculate value
 *                  }
 *                  ...
 *              }
 *          </code>
 *      </li>
 *
 *      <li> Run method TestModel::virtualUpdateDbSearchCache() so as to initialize "cache" in DB.
 *          This method should be called, when added new virtual attribute, or changed any virtual getter.<br/>
 *          <b>ATTENTION:</b> this is very hard operation for SQL/PHP, and should be used only in debug mode.
 *          This method calls {@link saveAttributes} for all AR-records in this table.
 *      </li>
 *
 *      <b>SEARCH BY VIRTUAL ATTRIBUTE (CGridView):</b><br/>
 *      Now you can enable search for attribute "_someFunc" (note, that name has prefix "_").<br/>
 *      By default, enabled "readOnly" mode for this attribute, and any attempt writing value throws exception.<br/>
 *      But you can disable this mode through method TestModel::setVirtualReadOnly(false) for writing of values from
 *      CGridView-filter.<br/>
 *
 *      For example we will enable search in admin-action of TestModelController:
 *      <li>
 *          Add "_someFunc" to TestModel::rules() so as to made "safe"-attribute, for example:
 *          <code>
 *              public function rules() {
 *                  return array(
 *                      array('_someFunc', 'safe', 'on' => 'search')
 *                  );
 *              }
 *          </code>
 *      </li>
 *
 *      <li>
 *          Add to method TestModel::search():
 *          <code>
 *              public function search() {
 *                  ...
 *                  $criteria->compare('_someFunc', $this->_someFunc);
 *                  ...
 *              }
 *          </code>
 *      </li>
 *
 *      <li>
 *          Add column to CGridView-widget (in admin.php view):
 *          <code>
 *               <? $this->widget('zii.widgets.grid.CGridView', array(
 *                   'id'                       => 'test-model-grid',
 *                   'dataProvider'             => $model->search(),
 *                   'filter'                   => $model,
 *                   'columns'                  => array(
 *                       'id',
 *                       ...
 *                       '_someFunc',
 *                       ...
 *                       array( 'class' => 'CButtonColumn' ),
 *                   ),
 *               )); ?>
 *          </code>
 *      </li>
 *
 *      <li>
 *          Typical search-action:
 *          <code>
 *              public function actionAdmin() {
 *                   $model = new TestModel('search');
 *
 *                   // Uncomment and run this method so as to initialize
 *                   // or refresh "cache" in DB (very hard operation!!)
 *                   // $model->virtualUpdateDbSearchCache();
 *
 *                   // this allows assign of any values from filter
 *                   $model->setVirtualReadOnly(false);
 *
 *                   $model->unsetAttributes();
 *                   if (isset($_GET['TestModel']))
 *                      $model->attributes = $_GET['TestModel'];
 *
 *                   $this->render('admin', array(
 *                       'model' => $model,
 *                   ));
 *               }
 *          </code>
 *      </li>
 * </ol>
 *
 * <b>ADVANCED USE CASE:</b><br/>
 *
 * &mdash; You can <b>change prefixes</b> of attributes and virtual getters
 * (override TestModel::virtualAttributePrefix and TestModel::virtualGetterPrefix).
 * But not recommended "get" prefix for virtual getter (like native yii-getter), because can be happen
 * confusion (in this case virtual getter can be read as native property "someFunc").
 *
 * &mdash; In addition, <b>we recommend "protected" virtual getters</b> so as to read/write
 * virtual attributes  only through "_someFunc" name.
 *
 * &mdash; In addition, <b>we not recommend empty prefix ("") for attribute</b> because can be happen
 * confusion with ordinary attributes of model.
 *
 * &mdash; For development you can override function (in TestModel class), that called
 * when you read of virtual attribute (before recalculating):
 * <code>
 *      public function onVirtualReadReadOnlyAttribute($virtualAttributeName) {
 *          echo '[[' . $virtualAttributeName . ']]';
 *      }
 * </code>
 *
 *
 *
 *
 * ------------------------------------ RUS ------------------------------------
 * Данный класс наследует {@link CActiveRecord} и позволяет выполнять поиск/сортировку по виртуальным атрибутам
 * (например, в {@link CGridView}).<br/>
 *
 * <b>ТЕРМИНЫ:</b>
 * <ol>
 *      <li>
 *          <b>Виртуальный атрибут</b> &mdash; "атрибут" модели, значение которого может вычисляться "на лету"
 *          (например, на основе других атрибутов).
 *          Для виртуального атрибута не существует собственного "свойства" класса, поэтому при его чтении, вызывается
 *          специальный метод (или "геттер"), вычисляющий и возвращающий значение
 *          (подробнее о виртуальных атрибутах см. в {@link http://www.yiiframework.com/wiki/167}).
 *      </li>
 *      <li>
 *          <b>Виртуальный геттер</b> &mdash; специальный метод класса модели, который взаимнооднозначно связан
 *          с определенным виртуальным атрибутом, служит для вычисления значения этого атрибута, и вызывается
 *          при его чтении.<br/>
 *          Отличается от yii-шных геттеров тем, что название метода может начинаться с произвольного префикса,
 *          задаваемого {@link virtualGetterPrefix}.
 *      </li>
 *      <li>
 *          С виртуальным атрибутом однозначно связан <b>оригинальный атрибут</b> модели (который представлен в схеме БД).
 *          Название соотвествующего поля в БД образуется добавлением префикса к названию виртуального атрибута.
 *      </li>
 * </ol>
 *
 *
 * <b>ОГРАНИЧЕНИЯ, ТРЕБОВАНИЯ.</b>
 * Данный класс применим только для определенного круга задач, ограниченного следующими требованиями:
 * <ol>
 *      <li>
 *          Значение виртуального атрибута (возвращаемое геттером) должно <b>однозначно</b> вычисляться по атрибутам
 *          этой же модели. Т.е. не должно зависеть от внешних переменных, например, текущего времени, атрибута
 *          связанной модели, кол-ва записей в таблице и т.д.
 *      </li>
 *      <li>
 *          Виртуальный геттер <b>всегда</b> должен возвращать значение, даже если оно не может быть корректно
 *          рассчитано если атрибуты, необходимые для вычисления, содержат некорректные значения, или просто не заданы).
 *          В этом случае необходимо возвращать null.
 *      </li>
 *      <li>
 *          Обновление существующих и вставка новых записей в БД должны производиться
 *          <b>исключительно методами текущей модели</b>.
 *      </li>
 * </ol>
 *
 * Все обращения (чтение/запись) идут по имени оригинального атрибута, т.е. с префиксом
 * {@link ActiveRecordVirtualAttribute::virtualAttributePrefix}.
 * Возвращаемое значение зависит от включенного режима {@link ActiveRecordVirtualAttribute::getVirtualReadOnly}.
 *
 *
 * --------------------------------------------------------------------------
 *
 * @property bool $virtualReadOnly  Enabled "readOnly" mode for virtual attributes?,
 *                                  see {@link ActiveRecordVirtualAttribute::getVirtualReadOnly}
 */
class ActiveRecordVirtualAttribute extends CActiveRecord // must be extended from CActiveRecord or its child-class
{
    /**
     * @return string Prefix of <b>original</b> attribute (in DB schema).
     * When reading and writing attribute values should be used this prefix.
     */
    public function virtualAttributePrefix()
    {
        return '_';
    }

    /**
     * @return string Prefix of method, that used for calculation of value of corresponding virtual attribute
     */
    public function virtualGetterPrefix()
    {
        return 'virtual';
    }

    /**
     * Return list of <b>virtual</b> attributes (without prefixes).<br/>
     * Corresponding names of <b>original</b> (in DB schema) should be begin with prefix {@link virtualAttributePrefix}.
     *
     * @return string[] List of <b>virtual</b> attributes
     */
    public function virtualAttributes()
    {
        return array();
    }

    private $_readOnly = true;

    // ---------------------------------------------------------------------------------------------------------
    // We catch all attempt of updating of DB-record, so as to recalculate and refresh of all virtual attributes
    // This is always done (independent of mode, see getVirtualReadMode)

    public function updateByPk($pk, $attributes, $condition = '', $params = array())
    {
        $attributes = array_merge($attributes, $this->_virtualAttributesCalculateAndRefresh(true));
        return parent::updateByPk($pk, $attributes, $condition, $params);
    }

    public function updateAll($attributes, $condition = '', $params = array())
    {
        $attributes = array_merge($attributes, $this->_virtualAttributesCalculateAndRefresh(true));
        return parent::updateAll($attributes);
    }

    public function insert($attributes = null)
    {
        $this->_virtualAttributesCalculateAndRefresh();
        return parent::insert($attributes);
    }

    // ---------------------------------------------------------------------------------------------------------
    // we catch cases of read of virtual attribute
    public function __get($name)
    {
        if ($this->_readOnly) {
            $virtualAttrName = $this->virtualConvertAttributeNameOriginal2Virtual($name);
            if ($this->_hasVirtualAttribute($virtualAttrName)) {
                $this->onVirtualReadReadOnlyAttribute($virtualAttrName);
                $this->_virtualAttributeCalculateAndRefresh($virtualAttrName);
                // return $this->getAttribute($virtualName);
            }
        }
        return parent::__get($name);
    }

    // we catch attempt write of value to virtual attribute in readOnly mode
    public function __set($name, $value)
    {
        $virtualAttrName = $this->virtualConvertAttributeNameOriginal2Virtual($name);
        if ($this->_hasVirtualAttribute($virtualAttrName)) {
            if ($this->_readOnly) {
                $this->onVirtualWriteReadOnlyAttribute($virtualAttrName);
            }
        }
        parent::__set($name, $value);
    }

    function init()
    {
        parent::init();
        $this->_checkVirtualAttributes();
    }

    /**
     * This method is called when an attempt is made to assign a value to the virtual attribute in readOnly mode
     * (see {@link getVirtualReadOnly}).<br/>
     * This is not critical case, because in readOnly mode you can't reading assigned value (always called getter).
     * But for the development of reliable applications, we don't recommend this (need throw exceptions).
     *
     * @param string $virtualAttributeName
     * @see getVirtualReadOnly
     */
    function onVirtualWriteReadOnlyAttribute($virtualAttributeName)
    {
        $this->_throwException('You can\'t assign value to virtual attribute "' . $virtualAttributeName . '" in readOnly mode');
    }

    /**
     * This method is called when you read virtual attribute in readOnly mode (before calling virtual getter)
     *
     * @param string $virtualAttributeName
     * @see getVirtualReadOnly
     */
    public function onVirtualReadReadOnlyAttribute($virtualAttributeName)
    {
    }

    /**
     * @return bool Enabled "readOnly" mode for virtual attributes? <br/>
     *   -- If <b>=TRUE</b>, any attempt to read the virtual attribute, its value is recalculated by getter.
     *      In this mode, any assignment of values ​​is disallow
     *      (throw exception, see {@link onVirtualWriteReadOnlyAttribute}).<br/>
     *   -- If <b>=FALSE</b>, virtual attribute behaves like ordinary attribute of CActiveRecord.
     *      Except that you can't save it in the DB (see below). <br/>
     *      In this case, allowed writing and reading any values, and reading of attribute doesn't call the getter.<br/><br/>
     *
     *      Whenever you <b>update the model (in DB)</b> values of all virtual attributes
     *      <b>recalculated independent</b> of this mode.
     * @see setVirtualReadOnly
     * @see onVirtualWriteReadOnlyAttribute
     */
    public function getVirtualReadOnly()
    {
        return $this->_readOnly;
    }

    /**
     * Enabled/disabled readOnly mode (see {@link getVirtualReadOnly})
     *
     * @param bool $readOnly
     */
    public function setVirtualReadOnly($readOnly)
    {
        $this->_readOnly = (bool)$readOnly;
    }

    /**
     * Synchronization: refresh values of all virtual attributes (in DB table). <br/>
     * This method should be called, when added new virtual attribute, or changed any virtual getter
     * (so as to synchronize virtual getters and their "cache" in DB).
     *
     * <b>ATTENTION:</b> this is very hard operation for SQL/PHP, and should be used only in debug mode.<br/>
     * This method calls {@link saveAttributes} for all AR-records in this table.
     */
    public function virtualUpdateDbSearchCache()
    {
        $provider = new CActiveDataProvider(get_called_class());
        $iterator = new CDataProviderIterator($provider, 100); // Чтобы не опрокинуть сервер

        $virtualAttributes = $this->virtualAttributes();
        if (!empty($virtualAttributes)) {
            $originalAttrName = $this->virtualConvertAttributeNameVirtual2Original(current($virtualAttributes));

            // we save only first virtual attribute, other will be recalculated and saved automatically
            foreach ($iterator as $AR) {
                /** @var $AR ActiveRecordVirtualAttribute */
                $AR->saveAttributes(array($originalAttrName));
            }
        }
    }

    /**
     * Recalculate and refresh value of all virtual attributes.
     *
     * @param bool $keyAsOriginalAttrName This flag selects type of key for returned array.<br/>
     *                                    If =FALSE, then keys is names of virtual attributes,
     *                                    else - names of original attributes (in DB schema)
     * @return array Values calculated by virtual getters in format [attributeName] => calculatedValue
     */
    private function _virtualAttributesCalculateAndRefresh($keyAsOriginalAttrName = false)
    {
        $virtualAttributes = $this->virtualAttributes();
        $result = array();
        foreach ($virtualAttributes as $virtualAttrName) {
            $key = $keyAsOriginalAttrName
                ? $this->virtualConvertAttributeNameVirtual2Original($virtualAttrName)
                : $virtualAttrName;

            $result[$key] = $this->_virtualAttributeCalculateAndRefresh($virtualAttrName);
        }
        return $result;
    }

    /**
     * Recalculate (always) and refresh (when in "readOnly" mode) value of given virtual attribute.
     *
     * @param string $virtualAttributeName
     * @return mixed    Value, that calculated by virtual getter
     */
    private function _virtualAttributeCalculateAndRefresh($virtualAttributeName)
    {
        // $this->_checkVirtualAttribute($virtualAttributeName); // disabled for performance, all attributes checked in AR::init()
        $getter = $this->virtualGetterName($virtualAttributeName);
        $originalAttributeName = $this->virtualConvertAttributeNameVirtual2Original($virtualAttributeName);
        $value = $this->{$getter}();

        if ($this->_readOnly) {
            $this->setAttribute($originalAttributeName, $value); // direct assignment of value of attribute, without php-magic (__set(...)).
        }
        return $value;
    }

    /**
     * Return name of virtual getter of given virtual attribute
     *
     * @param string $virtualAttributeName
     * @return string
     */
    protected function virtualGetterName($virtualAttributeName)
    {
        $prefix = $this->virtualGetterPrefix();
        return $prefix
            ? $prefix . ucfirst($virtualAttributeName)
            : $virtualAttributeName;
    }

    /**
     * Converts name of original attribute (in DB schema) in name of virtual attribute (without prefix)
     *
     * @param $originalAttributeName
     * @return null|string              Name of virtual attribute. Return null, if given name of original attribute
     *                                  does not contain defined prefix {@link virtualAttributePrefix}
     */
    protected function virtualConvertAttributeNameOriginal2Virtual($originalAttributeName)
    {
        $prefix = $this->virtualAttributePrefix();
        if (strpos($originalAttributeName, $prefix) === 0) {
            return substr($originalAttributeName, strlen($prefix));
        } else {
            return null;
        }
    }

    /**
     * Converts name of virtual attribute in name of original attribute (in DB schema)
     *
     * @param $virtualAttributeName
     * @return null|string              Name of original attribute
     */
    protected function virtualConvertAttributeNameVirtual2Original($virtualAttributeName)
    {
        return $this->virtualAttributePrefix() . $virtualAttributeName;
    }

    /**
     * Throws exception with given message
     *
     * @throws Exception
     */
    private function _throwException($msg = 'virtual attributes exception')
    {
        YiiBase::log($msg, CLogger::LEVEL_ERROR, get_called_class());
        throw new Exception($msg); // you can change class of exception
    }

    /**
     * Define existence of given virtual attribute
     *
     * @param string $virtualAttributeName
     * @return bool return TRUE, if model has given virtual attribute, else - FALSE
     */
    private function _hasVirtualAttribute($virtualAttributeName)
    {
        return ($virtualAttributeName && in_array($virtualAttributeName, $this->virtualAttributes()));
    }

    /**
     * Full check of given virtual attribute: <br/>
     *  - existence of virtual attribute <br/>
     *  - existence of corresponding original attribute (in DB schema) <br/>
     *  - existence of corresponding virtual getter
     *
     * @param string $virtualAttributeName
     * @throws Exception    if not found given virtual attribute or its getter
     */
    private function _checkVirtualAttribute($virtualAttributeName)
    {
        $getterName = $this->virtualGetterName($virtualAttributeName);
        $originalAttributeName = $this->virtualConvertAttributeNameVirtual2Original($virtualAttributeName);

        $beginStr = 'Invalid virtual attribute "' . $virtualAttributeName . ': ';
        if (!$this->_hasVirtualAttribute($virtualAttributeName)) {
            $this->_throwException($beginStr . ' not found');
        }
        if (!$this->hasAttribute($originalAttributeName)) {
            $this->_throwException($beginStr . ' check existing of db field "' . $originalAttributeName . '")');
        }
        if (!method_exists($this, $getterName)) {
            $this->_throwException($beginStr . ' check existing of virtual getter "' . $getterName . '")');
        }
        $this->{$getterName}();
    }

    /**
     * Full check of all virtual attributes (see {@link _checkVirtualAttribute})
     *
     * @throws Exception    if not found some virtual attribute or its getter
     */
    private function _checkVirtualAttributes()
    {
        $virtualAttributes = $this->virtualAttributes();
        foreach ($virtualAttributes as $virtualAttribute) {
            $this->_checkVirtualAttribute($virtualAttribute);
        }
    }
}