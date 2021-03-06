<?php namespace treehousetim\shopCart;

class cart implements totalInterface
{
	protected $items = [];
	protected $data = [];

	public $productsByMetal = [];
	protected $totals = [];
	protected $metalsCollection;

	public $totalTypes = [];
	protected $totalTypeLoader;

	protected $catalog;
	protected $formatter;
	protected $storageHandler;

	public function __construct( catalog $catalog )
	{
			//$this->metalsCollection = new cartMetalCollection();
			$this->catalog = $catalog;
			//$this->setFormatter( new productAmountFormatter() );
	}
	//------------------------------------------------------------------------
	public function __destruct()
	{
		if( session_status() == PHP_SESSION_ACTIVE )
		{
			$this->save();
		}
	}
	//------------------------------------------------------------------------
	public function getCatalog() : catalog
	{
		return $this->catalog;
	}
	//------------------------------------------------------------------------
	public function setStorageHandler( cartStorageInterface $storageHandler ) : self
	{
		$this->storageHandler = $storageHandler;
		return $this;
	}
	//------------------------------------------------------------------------
	public function setFormatter( productAmountFormatter $formatter ) : self
	{
		$this->formatter = $formatter;
		return $this;
	}
	//------------------------------------------------------------------------
	public function setTotalTypeLoader( catalogTotalTypeLoaderInterface $loader ) : self
	{
		$this->typeLoader = $loader;
		$this->populateTotalTypes();
		return $this;
	}
	
	//------------------------------------------------------------------------
	public function getDistinctItemQty() : string
	{
		return count( $this->items );
	}
	//------------------------------------------------------------------------
	public function getTotalQty() : string
	{
		$total = 0;
		foreach( $this->items as $item )
		{
			$total += $item->getQty();
		}

		return $total;
	}
	//------------------------------------------------------------------------
	public function addItem( cartItem $item ) : self
	{
			$this->items[ $item->getProduct()->getId() ] = $item;

			return $this;
	}
	//------------------------------------------------------------------------
	public function removeItem( cartItem $item ) : self
	{
			unset($this->items[ $item->getProduct()->getId() ]);

			return $this;
	}
	//------------------------------------------------------------------------
	public function addProduct( product $product, $qty ) : self
	{
		if( $this->hasItemForProductId( $product->getId() ) )
		{
				$cartItem = $this->getItemByProductId( $product->getId() );
				$cartItem->addQty( $qty );
		}
		else
		{
				$cartItem = new cartItem( $product );
				$cartItem->addQty( $qty );
				$this->addItem( $cartItem );
		}

		return $this;
	}
	//------------------------------------------------------------------------
	public function addData( cartData $data ) : self
	{
		$this->data[ $data->getType() ] = $data;	
		
		return $this;
	}
	
	//------------------------------------------------------------------------
	public function populateTotalTypes() : self
	{
		$this->typeLoader->resetType();
		$this->totalTypes = [];
		do
		{
			$type = $this->typeLoader->getType();
			$this->totalTypes[] = $type;
		} while( $this->typeLoader->nextType() );

		return $this;
	}
	//------------------------------------------------------------------------
	public function getTotalTypes() : array
	{
		$this->populateTotalTypes();
		return $this->totalTypes;
	}
	//------------------------------------------------------------------------
	public function getTotal( catalogTotalType $type ) : string
	{
		$total = 0;

		foreach( $this->items as $item )
		{
			$total = bcadd( $item->getTotalTypeAmount( $type ), $total );
		}

		return $total;
	}
	//------------------------------------------------------------------------
	public function getAmountOrdered( catalogTotalType $type ) : string 
	{
		$amountOrdered = 0;
		foreach ($this->items as $item) 
		{
			$amountOrdered = $item->getTotalAmount( $type );
		}
		return $amountOrdered;
	}
	//------------------------------------------------------------------------
	public function getAmountTotal( catalogTotalType $type ) : string
	{
		$totalAmount = 0;
		foreach ($this->items as $item) 
		{
			$totalAmount = bcadd($item->getTotalAmount( $type ), $totalAmount );
		}
		return $totalAmount;
	}
	//------------------------------------------------------------------------
	public function getAmountTotalFormatted( catalogTotalType $type )  : string
	{
		return $this->formatter->formatCartTotal( $type, $this->getAmountTotal( $type ) );	
	}
	//------------------------------------------------------------------------
	public function getTotalFormatted( catalogTotalType $type )  : string
	{
		return $this->formatter->formatCartTotal( $type, $this->getTotal( $type ) );
	}
	//------------------------------------------------------------------------
	public function formatTotalType( string $value, catalogTotalType $type )
	{
		//not used now
	}
	//------------------------------------------------------------------------
	public function requireProductId( $id )
	{
		if( ! $this->hasItemForProductId( $id ) )
		{
			throw new Exception( 'No Item' );
		}
	}
	//------------------------------------------------------------------------
	public function getItemByProductId( $id )
	{
		$this->requireProductId( $id );

		return $this->items[$id];
	}
	//------------------------------------------------------------------------
	public function hasItemForProductId( $id ) : bool
	{
		if( array_key_exists( $id, $this->items ) )
		{
			return true;
		}

		return false;
	}
	//------------------------------------------------------------------------
	public function updateItemQty( $id, $qty )
	{
		$this->requireProductId( $id );
		$this->getItemByProductId( $id )->updateQty( $qty );
	}
	//------------------------------------------------------------------------
	// public function getTotalCategories() : array
	// {
	// 	$out = [];
	// 	foreach( $this->items as $item )
	// 	{
	// 		$out = array_merge( $out, $item->getTotalCategories() );
	// 	}

	// 	return $out;
	// }
	//------------------------------------------------------------------------
	public function populate()
	{
		$model = new productModel();
		$rows = $model->fetchAll();

		foreach( $rows as $product )
		{
			$this->addItem( new cartItem( $product ) );
		}
	}
	//------------------------------------------------------------------------
	public function load() : self
	{
		$this->storageHandler->loadCart( $this );
		return $this;
	}
	//------------------------------------------------------------------------
	public function save()
	{
		$this->storageHandler->init( $this );

		foreach( $this->items as $item )
		{
			$this->storageHandler->saveCartItem( $item );
		}

		foreach( $this->data as $data )
		{
			$this->storageHandler->saveCartData( $data );
		}
	}
	//------------------------------------------------------------------------
	public function getCartData() : array
	{
		return $this->data;
	}
	//------------------------------------------------------------------------
	public function getDataByCartDataType( string $type ) 
	{

		if(count($this->data) > 0 )
		{
			
			foreach( $this->data as $cartData )
			{
				if( $cartData->isValidType( $type ) == false )
				{
					throw new \Exception("Invalid card data type : ".$type, 1);
				}

				if( $cartData->getType() == $type )
				{
					return $cartData->getDataArray();
				}
				else
				{
					continue;
				}
			}
			return [];
		}
		else
		{
			//throw new \Exception("No cart data set yet. ", 1);
			return (array) $this->data;
		}
	}
	//------------------------------------------------------------------------
	public function resetData() : self
	{
		$this->storageHandler->resetCartData();
		$this->data = array();
		return $this;
	}
	//------------------------------------------------------------------------
	public function nextData() : bool
	{
		next( $this->data );
		if( key( $this->data ) === null )
		{
			return false;
		}

		return true;
	}
	//------------------------------------------------------------------------
	public function getData() : cartData
	{
		return current( $this->data );
	}
	//------------------------------------------------------------------------
	public function getCartItems() : array
	{
		return $this->items;
	}
	//------------------------------------------------------------------------
	public function resetItems()
	{
		reset( $this->items );
	}
	//------------------------------------------------------------------------
	public function nextItem() : bool
	{
		next( $this->items );
		if( key( $this->items ) === null )
		{
			return false;
		}

		return true;
	}
	//------------------------------------------------------------------------
	public function getItem() : catalogTotalType
	{
		return current( $this->items );
	}
}