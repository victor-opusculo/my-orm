<?php

namespace VictorOpusculo\MyOrm;

require_once __DIR__ . '/Option.php';
require_once __DIR__ . '/None.php';
require_once __DIR__ . '/Some.php';

use Closure;

function isJson($value)
{
	if (is_numeric($value)) return false;
	if (is_object($value) || is_array($value)) return false;

	$obj = json_decode($value ?? '');
	if (json_last_error() === JSON_ERROR_NONE)
		return $obj;
	else
		return false;
}

function has_string_keys(array $array) 
{
	return count(array_filter(array_keys($array), 'is_string')) > 0;
}

/** 
 * @template T
 */
class DataProperty implements \JsonSerializable
{
	public const MYSQL_STRING = 's';
	public const MYSQL_INT = 'i';
	public const MYSQL_DOUBLE = 'd';
	public const MYSQL_BLOB = 'b';
	
	private string $dbType;
	private ?string $formFieldIdentifierName;
	private Closure $defaultValueClosure;
	private bool $encrypt = false;
	/** @var Option<T> */
	private Option $value;
	
	public $valueTransformer;  
	public $setValueTransformer;
	public $valueTransformerForDatabase;
	public $setValueTransformerFromDatabase;

	public function __construct(?string $formFieldIdentifierName, ?callable $defaultValueClosure, string $databaseType = self::MYSQL_STRING, bool $encrypt = false)
	{
		$this->formFieldIdentifierName = $formFieldIdentifierName;
		$this->dbType = $databaseType;
		$this->defaultValueClosure = $defaultValueClosure ? Closure::fromCallable($defaultValueClosure) : Closure::fromCallable(fn() => null);
		$this->encrypt = $encrypt;

		$this->value = Option::none();
	}
	
	public function __toString() : string
	{
		return print_r($this->value, true);
	}

	public function getValuesForHtmlForm(array $skip = []) : array
	{
		if ($this->formFieldIdentifierName && array_search($this->formFieldIdentifierName, $skip) === false)
			return [ $this->formFieldIdentifierName => htmlspecialchars($this->getValue()->unwrapOr(''), ENT_QUOTES) ];
		else
			return [];
	}
	
	public function getValue() : Option
	{
		return isset($this->valueTransformer) ? ($this->valueTransformer)($this->value) : $this->value;
	}
	
	public function setValue(mixed $value)
	{
		if (isset($this->setValueTransformer))
		{
			$val = ($this->setValueTransformer)($value);
			$this->value = $val instanceof Option ? $val : Option::some($val);
		}
		else
		{
			$this->value = $value instanceof Option ? $value : Option::some($value);
		}
	}

	public function setValueFromDatabase(mixed $value)
	{
		if (isset($this->setValueTransformerFromDatabase))
			$this->value = Option::some(($this->setValueTransformerFromDatabase)($value));
		else
			$this->setValue($value);
	}
	
	public function getValueForDatabase() : mixed
	{
		if ($this->value->unwrapOr(false))
		{
			return isset($this->valueTransformerForDatabase) 
			? ($this->valueTransformerForDatabase)($this->value)->unwrap() ?? ($this->defaultValueClosure)() 
			: $this->getValue()->unwrap() ?? ($this->defaultValueClosure)();
		}
		else
		{
			return isset($this->valueTransformerForDatabase) 
			? ($this->valueTransformerForDatabase)($this->value)->unwrapOrElse($this->defaultValueClosure) 
			: $this->getValue()->unwrapOrElse($this->defaultValueClosure);
		}
	}
	
	public function setEncrypt($value)
	{
		$this->encrypt = $value;
	}
	
	public function getEncrypt()
	{
		return $this->encrypt;
	}

	public function resetValue()
	{
		$this->value = Option::some($this->defaultValueClosure->__invoke());
	}

	public function setNone()
	{
		$this->value = Option::none();
	}
	
	public function getBindParamType()
	{
		return $this->dbType;
	}
	
	#[\ReturnTypeWillChange]
	public function jsonSerialize() 
	{
		return $this->getValue()->unwrapOrElse($this->defaultValueClosure);
	}
	
	public function fillFromFormInput(array $propsFromPost)
	{
		foreach ($propsFromPost as $name => $value)
			if ($name === $this->formFieldIdentifierName)
			{
				$this->setValue($value);
				break;
			}
	}
}

/**
 * @template P
 * @extends DataProperty<P>
 */
class DataObjectProperty extends DataProperty implements \IteratorAggregate
{
	/** @var P */
	private object $properties;
	
	public function __construct(?object $subProperties, bool $encrypt = false)
	{
		parent::__construct(null, null, DataProperty::MYSQL_STRING, $encrypt);
		$this->properties = $subProperties ?? new class {};
	}
	
	public function __toString() : string
	{
		return print_r($this->properties, true);
	}

	public function __get($name)
	{
		if (!isset($this->properties->$name)) return null;
		return $this->properties->$name->getValue();
	}
	
	public function __set(string $name, mixed $value)
	{
		if (!isset($this->properties->$name))
			throw new \Exception("Erro ao definir valor de propriedade inexistente \"$name\" em instância da classe " . self::class . '.');
		
		$this->properties->$name->setValue(Option::some($value));
	}

	public function getValuesForHtmlForm(array $skip = []) : array
	{
		$output = [];
		foreach ($this->properties as $prop)
			$output = [ ...$output, ...$prop->getValuesForHtmlForm($skip) ];
		return $output;
	}

	public function resetValue() : void
	{
		foreach ($this->properties as $po)
			$po->resetValue();
	}
	
	public function getIterator() : \Traversable
	{
		return new \ArrayIterator($this->properties);
	}
	
	/** @return Option<P> */
	public function getValue() : Option
	{
		if (is_null($this->properties))
			return Option::none();

		return Option::some($this);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() 
	{
		return $this->properties;
	}

	public function setNone() : void
	{
		$this->properties = null;
	}
	
	public function setValue($value) : void
	{
		if ($obj = isJson($value))
		{
			foreach ($obj as $p => $v)
				if (isset($this->properties->$p))
					$this->properties->$p->setValue($v);
		}
		else if (is_object($value) || (is_array($value) && has_string_keys($value)))
		{
			foreach ($value as $p => $v)
				if (isset($this->properties->$p))
					$this->properties->$p->setValue($v);
		}
		else if (is_null($value))
		{
			$this->properties = null;
		}
		else
		{
			throw new \Exception('Erro ao definir valor de objeto do tipo DataObjectProperty. Valor não é JSON, array associativo e nem objeto.');
		}
	}
	
	public function getValueForDatabase() : mixed
	{
		return json_encode($this->getValue()->unwrapOr('{}'));
	}
	
	public function fillFromFormInput(array $propsFromPost)
	{
		foreach ($this->properties as $prop)
			$prop->fillFromFormInput($propsFromPost);
	}
}