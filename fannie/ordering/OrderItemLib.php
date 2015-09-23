<?php

/**
  @class OrderItemLib
  
  Collection of methods to handle adding
  different types of items to special orders.
*/
class OrderItemLib
{
    private static $generic_item = array(
        'upc' => '',
        'sku' => '',
        'brand' => '',
        'description' => '',
        'normal_price' => 0,
        'special_price' => 0,
        'cost' => 0,
        'saleCost' => 0,
        'discounttype' => 0,
        'discountable' => 1,
        'caseSize' => 1,
        'department' => 0,
        'stocked' => 0,
        'vendorID' => 0,
        'vendorName' => '',
    );

    /**
      Lookup an item by UPC or SKU and return a
      standardized array representation
    */
    public static function getItem($upc)
    {
        $item = self::getItemByUPC($upc);
        if ($item !== false) {
            return $item;
        }

        $item = self::getItemBySKU($upc);
        if ($item !== false) {
            return $item;
        }

        $item = self::$generic_item;
        $item['description'] = $upc . ' SO';
        $item['upc'] = $upc;

        return $item;
    }

    /**
      Find an item by UPC
    */
    private static function getItemByUPC($upc)
    {
        $dbc = self::dbc();
        $upc = BarcodeLib::padUPC($upc);

        /**
          Lookup one:
          Item should be found in products, may optionally
          have info in the vendorItems table. Not in use items
          are purposely excluded. Their price and cost information
          may well not be up-to-date.
        */
        $prodP = $dbc->prepare('
            SELECT p.brand,
                p.description,
                p.normal_price,
                p.special_price,
                CASE WHEN p.cost=0 AND v.cost<>0 THEN p.cost ELSE v.cost END AS cost,
                COALESCE(v.saleCost, 0) AS saleCost,
                p.discounttype,
                p.discount AS discountable,
                COALESCE(v.units, 1) AS caseSize,
                1 AS stocked,
                p.default_vendor_id AS vendorID,
                COALESCE(n.vendorName, \'\') AS vendorName
                p.upc,
                COALESCE(v.sku, \'\') AS sku
            FROM products AS p
                LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
                LEFT JOIN vendors AS n ON p.default_vendor_id=n.vendorID
            WHERE p.upc=?
                AND p.inUse=1
        ');
        $prodR = $dbc->execute($prodP, array($upc));
        if ($prodR && $dbc->numRows($prodR) > 0) {
            return self::resultToItem($dbc, $prodR);
        }

        /**
          Lookup two:
          Not in products. Try vendorItems for not-stocked
          items.
        */
        $prodP = $dbc->prepare('
            SELECT v.brand,
                v.description,
                v.SRP AS normal_price
                0 AS special_price,
                v.cost,
                v.saleCost,
                0 AS discounttype,
                1 AS discountable,
                v.units AS caseSize,
                0 AS stocked,
                v.vendorID,
                COALESCE(n.vendorName, \'\') AS vendorName,
                v.upc,
                v.sku
            FROM vendorItems AS v 
                LEFT JOIN vendors AS n ON v.vendorID=n.vendorID
            WHERE v.upc=?
            ORDER BY v.vendorID
        ');

        $prodR = $dbc->execute($prodP, array($upc));
        if ($prodR && $dbc->numRows($prodR) > 0) {
            return self::resultToItem($dbc, $prodR);
        }

        // found nothing.
        return false;
    }

    private static function getItemBySKU($sku)
    {
        $dbc = self::dbc();
        // legacy behavior. + forces entry to be treated
        // as a SKU instead of a UPC.
        if (substr($sku, 0, 1) == '+') {
            $sku = substr($sku, 1);
        }

        /**
          Lookup one:
          Item should be found in products, may optionally
          have info in the vendorItems table. Not in use items
          are purposely excluded. Their price and cost information
          may well not be up-to-date.
        */
        $prodP = $dbc->prepare('
            SELECT p.brand,
                p.description,
                p.normal_price,
                p.special_price,
                CASE WHEN p.cost=0 AND v.cost<>0 THEN p.cost ELSE v.cost END AS cost,
                COALESCE(v.saleCost, 0) AS saleCost,
                p.discounttype,
                p.discount AS discountable,
                COALESCE(v.units, 1) AS caseSize,
                1 AS stocked,
                p.default_vendor_id AS vendorID,
                COALESCE(n.vendorName, \'\') AS vendorName,
                p.upc,
                COALESCE(v.sku, \'\') AS sku
            FROM products AS p
                LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
                LEFT JOIN vendors AS n ON p.default_vendor_id=n.vendorID
            WHERE v.sku LIKE ?
                AND p.inUse=1
        ');
        $prodR = $dbc->execute($prodP, array('%' . $sku));
        if ($prodR && $dbc->numRows($prodR) > 0) {
            return self::resultToItem($dbc, $prodR);
        }

        /**
          Lookup two:
          Not in products. Try vendorItems for not-stocked
          items.
        */
        $prodP = $dbc->prepare('
            SELECT v.brand,
                v.description,
                v.SRP AS normal_price
                0 AS special_price,
                v.cost,
                v.saleCost,
                0 AS discounttype,
                1 AS discountable,
                v.units AS caseSize,
                0 AS stocked,
                v.vendorID,
                v.upc,
                v.sku
                COALESCE(n.vendorName, \'\') AS vendorName
            FROM vendorItems AS v 
                LEFT JOIN vendors AS n ON v.vendorID=n.vendorID
            WHERE v.sku LIKE ?
            ORDER BY v.vendorID
        ');

        $prodR = $dbc->execute($prodP, array('%' . $sku));
        if ($prodR && $dbc->numRows($prodR) > 0) {
            return self::resultToItem($dbc, $prodR);
        }

        // found nothing.
        return false;
    }

    /**
      Helper: merge a SQL result with the generic item record
    */
    private static function resultToItem($dbc, $prodR)
    {
        $prodW = $dbc->fetchRow($prodR);
        $ret = self::$generic_item;
        foreach ($ret as $key => $val) {
            if (isset($prodW[$key])) {
                $ret[$key] = $prodW[$key];
            }
        }

        return $ret;
    }

    /**
      Helper: get database handle
    */
    private static function dbc()
    {
        return FannieDB::get(FannieConfig::config('OP_DB'));
    }

    /**
      Lookup special order department mapping
    */
    public static function mapDepartment($deptID)
    {
        $dbc = self::dbc();
        $mapP = $dbc->prepare('
            SELECT map_to
            FROM ' . FannieConfig::config('TRANS_DB') . $dbc->sep() . 'SpecialOrderDeptMap
            WHERE dept_ID=?
        ');
        $mapR = $dbc->execute($mapP, array($deptID));
        if ($mapR === false || $dbc->numRows($mapR) == 0) {
            return $deptID;
        } else {
            $row = $dbc->fetchRow($mapR);
            return $row['map_to'];
        }
    }

    /**
      Get the unit price for an item based on pricing
      rules
    */
    public static function getUnitPrice($item, $is_member)
    {
        if ($item['stocked'] && self::useSalePrice($item, $is_member)) {
            // only use sale price if it's a better deal
            $sale = $item['special_price'];
            $nonsale = self::stockedUnitPrice($item, $is_member);
            return $sale <= $nonsale ? $sale : $nonsale;
        } elseif ($item['stocked']) {
            return self::stockedUnitPrice($item, $is_member);
        } else {
            return self::notStockedUnitPrice($item, $is_member);
        }
    }

    public static function getCasePrice($item, $is_member)
    {
        return $item['caseSize'] * self::getUnitPrice($item, $is_member);
    }

    /**
      Apply pricing rules for items that are not
      currently stocked in-store
    */
    private static function notStockedUnitPrice($item, $is_member)
    {
        /**
          @Configurability: show be configurable to markdown from
          retail or mark up from wholesale
          WFC assumes vendorItems entries have SRPs even if they aren't
          stocked which is why it can work down from retail price.
        */
        if ($is_member) {
            return $item['discountable'] ? 0.85 * $item['normal_price'] : $item['normal_price'];
        } else {
            return $item['normal_price'];
        }
    }

    /**
      Apply pricing rules for items that are 
      currently stocked in-store
    */
    private static function stockedUnitPrice($item, $is_member)
    {
        /**
          @Configurability: show be configurable to markdown from
          retail or mark up from wholesale
        */
        if ($is_member) {
            return $item['discountable'] ? 0.85 * $item['normal_price'] : $item['normal_price'];
        } else {
            return $item['normal_price'];
        }
    }

    /**
      Decide if the sale price be used for this item
    */
    private static function useSalePrice($item, $is_member)
    {
        /**
          @Configurability: need to be able to turn off sale
          pricing entirely if desired rather than just for
          specific batch types
        */
        if ($item['special_price'] == 0) {
            return false;
        } elseif ($item['discounttype'] == 1) {
            return self::saleApplies($item);
        } elseif ($item['discounttype'] == 2 && $is_member) {
            return self::saleApplies($item);
        } 

        return false;
    }

    /**
      Find the batch and check whether that batch type
      is so-eligible
    */
    private static function saleApplies($item)
    {
        $dbc = self::dbc();
        $saleP = $dbc->prepare('
            SELECT t.specialOrderEligible
            FROM batchList AS l
                INNER JOIN batches AS b ON l.batchID=b.batchID
                INNER JOIN batchType AS t ON t.batchTypeID=b.batchType
            WHERE l.upc=?
                AND l.salePrice=?
                AND b.startDate <= ' . $dbc->curdate() . '
                AND b.endDate >= ' . $dbc->curdate()
        );  
        $eligible = $dbc->getValue($saleP, array($item['upc'], $item['special_price']));

        return $eligible ? true : false;
    }

    /**
      Check whether a manual quantity is required for this item
      @return [boolean] false if not required or
        [integer] default quantity
    */
    public static function manualQuantityRequired($item)
    {
        /**
          @Configurability: this is extremely WFC-specific.
          A field in SpecialOrderDeptMap could list a default
          manual quantity rather than hardcoding values here.
        */
        $dbc = self::dbc(); 
        $superP = $dbc->prepare("
            SELECT superID 
            FROM superdepts 
            WHERE dept_ID=?");
        $req = $dbc->getValue($superP, array($item['department']));
        if ($req == 5) {
            return 3;
        } else {
            return false;
        }
    }
}
