<?php
class KalturaGenericDistributionProfile extends KalturaDistributionProfile
{
	/**
	 * @insertonly
	 * @var int
	 */
	public $genericProviderId;
	
	/**
	 * @var KalturaGenericDistributionProfileAction
	 */
	public $submitAction;
	
	/**
	 * @var KalturaGenericDistributionProfileAction
	 */
	public $updateAction;	
	
	/**
	 * @var KalturaGenericDistributionProfileAction
	 */
	public $deleteAction;	
	
	/**
	 * @var KalturaGenericDistributionProfileAction
	 */
	public $fetchReportAction;	
	
	/*
	 * mapping between the field on this object (on the left) and the setter/getter on the object (on the right)  
	 */
	private static $map_between_objects = array 
	(
		'genericProviderId',	
	);
		 
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	private static $actions = array 
	(
		'submit',
		'update',
		'delete',
		'fetchReport',
	);
	
	public function toObject($object = null, $skip = array())
	{
		if(is_null($object))
			$object = new GenericDistributionProfile();
			
		$object = parent::toObject($object, $skip);
			
		foreach(self::$actions as $action)
		{
			if(!$this->$action)
				continue;
				
			$typeReflector = KalturaTypeReflectorCacher::get(get_class($this->$action));
			
			foreach ( $this->$action->getMapBetweenObjects() as $this_prop => $object_prop )
			{
			 	if ( is_numeric( $this_prop) ) $this_prop = $object_prop;
				if (in_array($this_prop, $skip)) continue;
				
				$value = $this->$action->$this_prop;
				if ($value !== null)
				{
					$propertyInfo = $typeReflector->getProperty($this_prop);
					if (!$propertyInfo)
					{
			            KalturaLog::alert("property [$this_prop] was not found on object class [" . get_class($object) . "]");
					}
					else if ($propertyInfo->isDynamicEnum())
					{
						$value = kPluginableEnumsManager::apiToCore($propertyInfo->getType(), $value);
					}
					
					if ($value !== null)
					{
						$setter_callback = array($object, "set{$object_prop}");
						if (is_callable($setter_callback))
					 	    call_user_func_array($setter_callback, array($value, $action));
				 	    else 
			            	KalturaLog::alert("setter for property [$object_prop] was not found on object class [" . get_class($object) . "]");
					}
				}
			}
		}
		
		return $object;		
	}

	public function fromObject($object)
	{
		parent::fromObject($object);
		
		foreach(self::$actions as $action)
		{
			if(!$this->$action)
				$this->$action = new KalturaGenericDistributionProfileAction();
				
			$reflector = KalturaTypeReflectorCacher::get(get_class($this->$action));
			$properties = $reflector->getProperties();
			
			foreach ( $this->$action->getMapBetweenObjects() as $this_prop => $object_prop )
			{
				if ( is_numeric( $this_prop) ) 
				    $this_prop = $object_prop;
				    
				if(!isset($properties[$this_prop]) || $properties[$this_prop]->isWriteOnly())
					continue;
					
	            $getter_callback = array ( $object ,"get{$object_prop}"  );
	            if (is_callable($getter_callback))
	            {
	                $value = call_user_func($getter_callback, $action);
	                if($properties[$this_prop]->isDynamicEnum())
	                	$value = kPluginableEnumsManager::coreToApi($properties[$this_prop]->getType(), $value);
	                	
	                $this->$action->$this_prop = $value;
	            }
	            else
	            { 
	            	KalturaLog::alert("getter for property [$object_prop] was not found on object class [" . get_class($object) . "]");
	            }
			}
		}
	}
}