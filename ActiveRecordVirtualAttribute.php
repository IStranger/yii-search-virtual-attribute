<?php
/**
 * ActiveRecordVirtualAttribute class file.
 *
 * @author G.Azamat <m@fx4.ru>
 * @link http://www.fx4.ru/
 * @link http://github.com/IStranger/yiiSearchVirtualAttribute
 * @version 0.2 (2014-08-02)
 * @since 1.1.14
 */

/**
 * This class extends {@link CActiveRecord} and can be used search by "<b>virtual attributes</b>"
 * (for example, in filter of {@link CGridView} widget).<br/>
 * Value of the virtual attribute is dynamically calculated by method (so-called "<b>virtual getter</b>")
 * when you read this attribute. <br/>
 * This class creates <b>search cache in DB</b> (adds column in table) and updates him after each updating of record.<br/>
 * The said search cache can be used for forming of search criteria (like ordinary attributes).<br/>
 *
 * <b>LIMITATIONS:</b>
 * <ol>
 *      <li> Currently class works only for MySQL. </li>
 *      <li> All update operations should be executed only through ActiveRecord-model (which extends this class),
 *          in order to update of search cache. </li>
 *      <li> Search cache must be updated for all records in table, after each changing of any virtual getter
 *          or adding new virtual attribute (you must call update method). </li>
 *      <li> By default, operation of bulk update disabled in this class, because update of search
 *          cache may be very difficult for PHP/SQL. </li>
 *      <li> Virtual getter must use only attributes of AR model (or constants). </li>
 *      <li> Not tested for tables with large number of records (maybe low performance of queries). </li>
 * </ol>
 *
 * <b>INSTALLATION, TYPICAL USE CASE</b>
 *
 * If you wish add new virtual attribute (for example "someFunc") to your model, and enable search by this attribute:
 * <ol>
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
 *      <li> Run method TestModel::virtualUpdateDbSearchCache() in order to initialize "cache" in DB.
 *          This method should be called, when added new virtual attribute, or changed any virtual getter.<br/>
 *          <b>ATTENTION:</b> this very difficult operation (for SQL/PHP) should be used carefully.
 *          This method calls {@link saveAttributes} for all AR-records in this table.
 *      </li>
 *
 *      <b>SEARCH BY VIRTUAL ATTRIBUTE (CGridView):</b><br/>
 *      Now you can enable search for attribute "someFunc".
 *      By default, enabled "readOnly" mode for this attribute, and any attempt writing a value throws exception.
 *      But you can disable this mode through method TestModel::setVirtualReadOnly(false) in order to write
 *      the values from CGridView-filter.<br/>
 *
 *      For example we will enable search in actionAdmin of TestModelController:
 *      <li>
 *          Add "someFunc" in TestModel::rules() in order this attribute was "safe", for example:
 *          <code>
 *              public function rules() {
 *                  return array(
 *                      array('someFunc', 'safe', 'on' => 'search')
 *                  );
 *              }
 *          </code>
 *      </li>
 *
 *      <li>
 *          Add to method TestModel::search():
 * <code>
 *  public function search() {
 *      ...
 *      $criteria->compare($this->virtualAttributeSqlExpression('someFunc'), $this->someFunc, true);
 *      ...
 *  }
 * </code>
 *
 *          In additional, you can use "scope" for forming criteria:
 *          <code>
 *              public function search() {
 *                  ...
 *                  $modelNew = new TestModel('search');
 *                  $modelNew->byVirtualAttribute('someFunc', $this->someFunc, true);
 *                  $criteria->mergeWith($modelNew->dbCriteria);
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
 *                       'someFunc',
 *                       ...
 *                       array( 'class' => 'CButtonColumn' ),
 *                   ),
 *               )); ?>
 *          </code>
 *      </li>
 *
 *      <li>
 *          Note, by default, assignment values to virtual attribute throws exception.
 *          In order to write values from filter of CGridView, you should disable "readOnly" mode (see "setVirtualReadOnly"). <br/>
 *          Typical search-action:
 *          <code>
 *              public function actionAdmin() {
 *                   $model = new TestModel('search');
 *
 *                   // Uncomment and run this method in order to initialize
 *                   // or refresh "cache" in DB (difficult operation!)
 *                   // $model->virtualUpdateDbSearchCache();
 *
 *                   // This allows assign any values from filter form
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
 * &mdash; You can <b>change prefix</b> of virtual getters (override TestModel::virtualGetterPrefix).
 * Can be used prefixes "get" (like native yii-getter) or "", but we recommended use other prefixes
 * in order to avoid confusion with other methods.
 *
 * &mdash; In addition, <b>we recommend "protected" virtual getters</b> in order to read/write
 * virtual attributes only through "someFunc" name.
 *
 * &mdash; In addition, <b>we recommend any prefix for virtual attributes</b> (for example "_") in order to avoid
 * confusion with ordinary attributes of model.
 *
 * &mdash; For development you can override function (in TestModel class), which called
 * when you read of virtual attribute (before recalculating):
 * <code>
 *      public function onVirtualBeforeGetterCalculate($virtualAttributeName) {
 *          echo '[[' . $virtualAttributeName . ']]';
 *      }
 * </code>
 *
 * &mdash; In order to enable function of bulk update (that disabled by default), you can override method "onBeforeBulkUpdate".
 * After this you can use methods "updateAll" and "updateByPk" for bulk update of records.
 * In this case, after bulk update will be called method "onAfterBulkUpdate", that updates the search cache for updated rows.
 *
 * <code>
 *      public function onBeforeBulkUpdate($criteria)
 *      }
 * </code>
 *
 * @property bool $virtualReadOnly  Enabled "readOnly" mode for virtual attributes?,
 *                                  see {@link ActiveRecordVirtualAttribute::getVirtualReadOnly}
 */
class ActiveRecordVirtualAttribute extends CActiveRecord // must be extended from CActiveRecord or its child-class
{
    /**
     * @var array All virtual attributes in format [attributeName] => value
     */
    private $_virtualAttributes = array();
    private $_readOnly = true;
    private $_separatorColumn = ',';
    private $_separatorValue = ':';

    /**
     * @return string Name of attribute (field in DB schema), that holds "search cache" of virtual attributes
     */
    public function virtualSearchCacheField()
    {
        return '_search_cache_virtual_attributes';
    }

    /**
     * @return string Prefix of method, that used for calculation of value of corresponding virtual attribute
     */
    public function virtualGetterPrefix()
    {
        return 'virtual';
    }

    /**
     * Returns list of <b>names</b> of virtual attributes.
     *
     * @return string[] List of names
     */
    public function virtualAttributes()
    {
        return array();
    }

    // ---------------------------------------------------------------------------------------------------------
    // We catch all attempt of updating of single DB-record, in order to recalculate and refresh of all virtual
    // attributes of this record. This is always done (independent of mode, see getVirtualReadMode).
    // Note, case of bulk update maybe require difficult operations (for refresh of search cache),
    // therefore this operation disabled by default.

    public function updateByPk($pk, $attributes, $condition = '', $params = array())
    {
        if (!is_array($pk) || (!isset($pk[0]) && $pk !== array())) { // single key or single composite key

            $this->_recalculateAndRefreshAllAttributes();
            $attributes = array_merge($attributes, $this->_getSearchCacheAttributeAsArray());
            return parent::updateByPk($pk, $attributes, $condition, $params);

        } else { // multiple primary keys

            $criteria = new CDbCriteria();
            $criteria->addInCondition($this->getTableSchema()->primaryKey, $pk);
            $criteria->addCondition($condition);
            $criteria->params = array_merge($criteria->params, $params);

            $this->onBeforeBulkUpdate($criteria);
            $result = parent::updateByPk($pk, $attributes, $condition, $params);
            $this->onAfterBulkUpdate($criteria);
            return $result;
        }
    }

    public function updateAll($attributes, $condition = '', $params = array())
    {
        $criteria = new CDbCriteria();
        $criteria->addCondition($condition);
        $criteria->params = $params;

        $this->onBeforeBulkUpdate($criteria);
        $result = parent::updateAll($attributes, $condition, $params);
        $this->onAfterBulkUpdate($criteria);
        return $result;
    }

    public function insert($attributes = null)
    {
        $this->_recalculateAndRefreshAllAttributes();
        if (is_array($attributes)) {
            $attributes = array_merge($attributes, $this->virtualSearchCacheField());
        }
        return parent::insert($attributes);
    }

    // ---------------------------------------------------------------------------------------------------------


    // we catch cases of read of virtual attribute
    public function __get($name)
    {
        if ($this->hasVirtualAttribute($name)) {
            if ($this->_readOnly) {
                $this->_recalculateAndRefreshAttribute($name);
            }
            return $this->_virtualAttributes[$name];
        }
        return parent::__get($name);
    }

    // we catch attempt write of value to virtual attribute in readOnly mode
    public function __set($name, $value)
    {
        if ($this->hasVirtualAttribute($name)) {
            if ($this->_readOnly) {
                $this->onVirtualWriteReadOnlyAttribute($name);
            } else {
                $this->_virtualAttributes[$name] = $value;
            }
        } else {
            parent::__set($name, $value);
        }
    }

    public function __isset($name)
    {
        return $this->hasVirtualAttribute($name) || parent::__isset($name);
    }

    public function __unset($name)
    {
        if ($this->hasVirtualAttribute($name)) {
            $this->_throwException('Virtual attribute "' . $name . '" cannot be unset');
        } else {
            parent::__unset($name);
        }
    }

    public function init()
    {
        parent::init();
        $this->_checkVirtualAttributes();

        // Bulk assignment =null to all virtual attributes, for example, if readOnly=false by default
        $virtualAttributes = $this->virtualAttributes();
        foreach ($virtualAttributes as $attributeName) {
            $this->_virtualAttributes[$attributeName] = null;
        }
    }

    public function attributeNames() // for AR::unsetAttributes(), AR::getAttributes()
    {
        return array_merge(parent::attributeNames(), $this->virtualAttributes());
    }

    public function getAttributes($names = true)
    {
        // we split ordinary and virtual attributes
        if (is_array($names)) {
            $virtualAttributeNames = array_intersect($names, $this->virtualAttributes());
        } else {
            $names = $this->attributeNames();
            $virtualAttributeNames = $this->virtualAttributes();
        }
        $baseAttributeNames = array_diff($names, $virtualAttributeNames);

        // array with virtual values
        $virtualAttributes = array();
        foreach ($virtualAttributeNames as $virtualName) {
            $virtualAttributes[$virtualName] = $this->{$virtualName};
        }

        return array_merge(parent::getAttributes($baseAttributeNames), $virtualAttributes);
    }

    public function hasAttribute($name)
    {
        return $this->hasVirtualAttribute($name) || parent::hasAttribute($name);
    }

    public function getAttribute($name)
    {
        if ($this->hasVirtualAttribute($name)) {
            return $this->{$name};
        } else {
            return parent::getAttribute($name);
        }
    }

    public function setAttribute($name, $value)
    {
        if ($this->hasVirtualAttribute($name)) {
            $this->{$name} = $value;
            return true;
        } else {
            return parent::setAttribute($name, $value);
        }
    }

    /**
     * This method is called when an attempt is made to assign a value to the virtual attribute in readOnly mode
     * (see {@link getVirtualReadOnly}).
     *
     * @param string $virtualAttributeName
     * @see getVirtualReadOnly
     */
    public function onVirtualWriteReadOnlyAttribute($virtualAttributeName)
    {
        $this->_throwException('You can\'t assign value to virtual attribute "' . $virtualAttributeName . '" in readOnly mode');
    }

    /**
     * This method is called when you read virtual attribute in readOnly mode (before calling virtual getter)
     *
     * @param string $virtualAttributeName
     * @see getVirtualReadOnly
     */
    public function onVirtualBeforeGetterCalculate($virtualAttributeName)
    {
    }


    /**
     * This method called before execution of operation of bulk update.
     * By default throws exception, because operation of update of search cache may be very difficult
     * (see {@link onAfterBulkUpdate}).
     *
     * @param CDbCriteria $criteria Criteria for selecting rows, which will be updated
     */
    public function onBeforeBulkUpdate($criteria)
    {
        $className = get_called_class();
        $this->_throwException( // Comment this line for update of search cache (see onAfterBulkUpdate).
            'Warning: attempt of bulk update. By default this behavior is disabled in VirtualAttribute class, ' .
            'because operation of update of search cache may be very difficult. ' .
            'For correct search by virtual attributes you should override methods ' .
            '"' . $className . '::onBeforeBulkUpdate".'
        );
    }

    /**
     * This method called after execution of operation of bulk update.<br/>
     * By default, updates the search cache for updated rows.<br/>
     *
     * <b>PLEASE NOTE</b>, it may be difficult operation!!
     *
     * @param CDbCriteria $criteria Criteria for selecting rows, which were updated
     * @see onBeforeBulkUpdate
     */
    public function onAfterBulkUpdate($criteria)
    {
        $this->virtualUpdateDbSearchCache($criteria);
    }

    /**
     * @return bool Enabled "readOnly" mode for virtual attributes? <br/>
     *   -- If <b>=TRUE</b>, any attempt to read the virtual attribute, its value is recalculated by getter.
     *      In this mode, any assignment of values ​​is disallowed
     *      (throw exception, see {@link onVirtualWriteReadOnlyAttribute}).<br/>
     *      For this mode see also {@link onVirtualBeforeGetterCalculate}<br/>
     *   -- If <b>=FALSE</b>, virtual attribute behaves like ordinary attribute of CActiveRecord.
     *      Except that you can't save it in the DB (see below). <br/>
     *      In this case, allowed writing and reading any values, and reading of attribute doesn't call the getter.<br/><br/>
     *
     *      Whenever you <b>update the model (in DB)</b> values of all virtual attributes
     *      <b>recalculated independent</b> of this mode.
     * @see setVirtualReadOnly
     * @see onVirtualBeforeGetterCalculate
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
     * Refreshes "search cache" (in DB table) for all records, that will be selected by given criteria. <br/>
     * This method should be called, when was added new virtual attribute, or was changed any virtual getter
     * (in order to "synchronize" virtual getters and their "cache" in DB).
     *
     * <b>ATTENTION:</b> this very difficult operation (for SQL/PHP) should be used carefully.<br/>
     * This method calls {@link saveAttributes} for all (selected by given criteria) AR-records in this table.
     */
    public function virtualUpdateDbSearchCache($criteria = null)
    {
        $provider = new CActiveDataProvider(get_called_class());
        if ($criteria) {
            $provider->setCriteria($criteria);
        }

        $iterator = new CDataProviderIterator($provider, 100); // in order to avoid the peak load

        if (count($this->virtualAttributes()) > 0) {
            $this->_addSearchCacheColumnIfNotExist();

            $cacheAttributeName = $this->virtualSearchCacheField();
            foreach ($iterator as $AR) {
                /** @var $AR ActiveRecordVirtualAttribute */
                $AR->saveAttributes(array($cacheAttributeName));
            }
        }
    }

    /**
     * Adds condition of search by given virtual attribute in criteria of current model.<br/>
     * See also description of params {@link CDbCriteria::compare}.
     *
     * @param string $virtualAttributeName
     * @param string $value the column value to be compared with. If the value is a string,
     *                              the aforementioned intelligent comparison will be conducted. If the value is
     *                              an array, the comparison is done by exact match of any of the value in the array.
     *                              If the string or the array is empty, the existing search condition will not be modified.
     * @param bool $partialMatch whether the value should consider partial text match (using LIKE and NOT LIKE
     *                              operators). Defaults to false, meaning exact comparison.
     * @param string $operator the operator used to concatenate the new condition with the existing one. Defaults to 'AND'.
     * @return $this                Returns self (with modified criteria)
     */
    public function byVirtualAttribute($virtualAttributeName, $value, $partialMatch = false, $operator = 'AND')
    {
        $this->dbCriteria
            ->compare($this->virtualAttributeSqlExpression($virtualAttributeName), $value, $partialMatch, $operator);
        return $this;
    }

    /**
     * Returns SQL-expression for extracting value of given virtual attribute,
     * which can used as "column" name, for example in {@link CDbCriteria::compare}
     *
     * @param string $virtualAttributeName
     * @return string
     */
    public function virtualAttributeSqlExpression($virtualAttributeName)
    {
        // Only for MySQL:
        $attrName = $this->_separatorColumn . $virtualAttributeName . $this->_separatorValue;
        return "SUBSTRING_INDEX(SUBSTRING_INDEX(" .
        $this->virtualSearchCacheField() . ",'{$attrName}',-1),'{$this->_separatorColumn}',1)";
        // Example: SUBSTRING_INDEX(SUBSTRING_INDEX(_search_cache_virtual_attributes,',birthSeason:',-1),',',1)
    }

    /**
     * Checks whether this AR has the given virtual attribute
     *
     * @param string $virtualAttributeName
     * @return bool return TRUE, if model has given virtual attribute, else - FALSE
     */
    public function hasVirtualAttribute($virtualAttributeName)
    {
        return ($virtualAttributeName && in_array($virtualAttributeName, $this->virtualAttributes()));
    }

    /**
     * Recalculates and refreshes value of all virtual attributes and "search cache" attribute
     * (independent of readOnly mode).<br/>
     * This method updates only {@link _virtualAttributes}.
     */
    private function _recalculateAndRefreshAllAttributes()
    {
        $searchCache = array();
        $virtualAttributes = $this->virtualAttributes();
        foreach ($virtualAttributes as $attributeName) {
            $value = $this->_recalculateAndRefreshAttribute($attributeName);
            $searchCache[] = $attributeName . $this->_separatorValue . $value;
        }

        // update "search cache" attribute
        $cacheAttributeName = $this->virtualSearchCacheField();
        $this->{$cacheAttributeName} = $this->_separatorColumn . join($this->_separatorColumn, $searchCache) . $this->_separatorColumn;
    }

    /**
     * Recalculates and refreshes value of given virtual attribute (independent of readOnly mode).<br/>
     * This method updates only {@link _virtualAttributes}.
     *
     * @param string $virtualAttributeName
     * @return mixed Returns value calculated by getter
     */
    private function _recalculateAndRefreshAttribute($virtualAttributeName)
    {
        $this->onVirtualBeforeGetterCalculate($virtualAttributeName);
        $getter = $this->virtualGetterName($virtualAttributeName);
        return ($this->_virtualAttributes[$virtualAttributeName] = $this->{$getter}());
    }

    /**
     * Returns search cache attribute in format: [ 'searchAttributeName' => value ]
     */
    private function _getSearchCacheAttributeAsArray()
    {
        $cacheAttributeName = $this->virtualSearchCacheField();
        return array(
            $cacheAttributeName => $this->{$cacheAttributeName}
        );
    }


    /**
     * Returns name of virtual getter for given virtual attribute
     *
     * @param string $virtualAttributeName
     * @return string Name of method
     */
    protected function virtualGetterName($virtualAttributeName)
    {
        $prefix = $this->virtualGetterPrefix();
        return $prefix
            ? $prefix . ucfirst($virtualAttributeName)
            : $virtualAttributeName;
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
     * Full check of given virtual attribute: <br/>
     *  - existence of virtual attribute <br/>
     *  - existence of corresponding virtual getter
     *
     * @param string $virtualAttributeName
     * @throws Exception    if not found given virtual attribute or its getter
     */
    private function _checkVirtualAttribute($virtualAttributeName)
    {
        $getterName = $this->virtualGetterName($virtualAttributeName);

        $beginStr = 'Invalid virtual attribute "' . $virtualAttributeName . ': ';
        if (!$this->hasVirtualAttribute($virtualAttributeName)) {
            $this->_throwException($beginStr . ' not found');
        }
        if (!method_exists($this, $getterName)) {
            $this->_throwException($beginStr . ' check existence of virtual getter "' . $getterName . '")');
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

    /**
     * @return bool Checks existence of column (in DB schema) for "search cache"
     * (see {@link virtualSearchCacheField})
     */
    private function _hasSearchCacheColumn()
    {
        return in_array($this->virtualSearchCacheField(), $this->tableSchema->columnNames);
    }

    /**
     * Method checks existence of column (in DB schema) for "search cache" (see {@link virtualSearchCacheField})
     * and creates it if not exist.
     */
    private function _addSearchCacheColumnIfNotExist()
    {
        if (!$this->_hasSearchCacheColumn()) {
            $this->dbConnection->createCommand()
                ->addColumn($this->tableName(), $this->virtualSearchCacheField(), 'string');
        }
        // refresh schema cache for a table (even if enabled option 'db.schemaCachingDuration')
        Yii::app()->db->schema->getTable($this->tableName(), true);
        // Yii::app()->db->schema->getTables();    // Load all tables of the application in the schema
        // Yii::app()->db->schema->refresh();      // clear the cache of all loaded tables
        $this->refreshMetaData(); // Regenerate the AR meta data (the latest available table schema)
    }
}