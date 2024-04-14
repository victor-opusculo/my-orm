<?php

namespace VictorOpusculo\MyOrm;

/**
 * @template T
 */
abstract class Option 
{
    protected function __construct(mixed $value)
    {
        $this->value = $value;
    }

	/** @var T */
    public readonly mixed $value;

	/** @return T */
	public function unwrap() : mixed
	{
		if (!isset($this->value))
			throw new \Exception('Não há valor no objeto Option!');
			
		return $this->value;
	}
	
	/** @return T|mixed */
	public function unwrapOr(mixed $alternativeValue) : mixed
	{
		if (!isset($this->value))
			return $alternativeValue;
			
		return $this->value;
	}
	
	/** @return T|mixed */
	public function unwrapOrElse(callable $func) : mixed
	{
		if (!isset($this->value))
			return $func();
			
		return $this->value;
	}

    public static function none() { return new None(); }
    public static function some(mixed $value) { return new Some($value); }

    public static function match(self $opt, callable $onSome, callable $onNone)
    {
        return ($opt instanceof Some) ? $onSome($opt->value) : $onNone();
    }
}



