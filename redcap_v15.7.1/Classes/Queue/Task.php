<?php
namespace Vanderbilt\REDCap\Classes\Queue;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use Opis\Closure\SerializableClosure as SerializableClosure73;

class Task implements TaskInterface
{
    private $closure;

    public function __construct($callable) {
        $unboundClosure = Closure::bind($callable, null, null); // set scope and this to null 
        $this->closure = $unboundClosure;
    }

    public static function fromSerializedData($data) {
        $serializer = new Serializer();
        
        $callable = $serializer->unserialize($data);
        return new Task($callable);
    }

    public function getClosure() {
        return $this->closure;
    }

    public function serialize() {
        $serializer = new Serializer();
        $serializedClosure = $serializer->serialize($this->closure);
        return $serializedClosure;
    }

    // public function __serialize() {
    //     $serializableClosure = new SerializableClosure($this->closure);
    //     return ['closure' => $serializableClosure];
    // }

    // public function __unserialize($data) {
    //     /** @var SerializableClosure $closure */
    //     $serializableClosure = $data['closure'];
    //     if(!($serializableClosure instanceof SerializableClosure)) throw new Exception("Error unserializing the closure", 400);
    //     $closure = $serializableClosure->getClosure();
    //     if(!($closure instanceof Closure)) throw new Exception("Error extracting the closure", 400);
        
    //     $this->closure = $closure;
    // }

    public function handle() {
        return call_user_func_array($this->closure, []);
    }
  
    
}