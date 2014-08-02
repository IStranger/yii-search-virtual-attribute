yiiSearchVirtualAttribute
=========================

This class (for yii-framework) can be used for <b>search by virtual attributes</b>
(for example, in ajax filter of <b>CGridView</b> widget).

Value of the virtual attribute is dynamically calculated by method (so-called "virtual getter"), when you read this attribute.
This class creates <b>"search cache"</b> in DB (adds column in table) and updates him after each updating of record.
The said "search cache" can be used for forming of search criteria (like ordinary AR-attributes).

Detailed description with examples you can find in phpDoc (ActiveRecordVirtualAttribute.php).