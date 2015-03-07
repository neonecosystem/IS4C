<?php
$INIT_MENU = array(
    'Item Maintenance' => array(
        'Manage Products' => '__header__',
        'By UPC/SKU or Brand Prefix' => 'item/ItemEditorPage.php',
        'Product List and Tool' => 'item/ProductListPage.php',
        'Advanced Search' => 'item/AdvancedItemSearch.php',
        'Manage Departments' => '__header__',
        'Super Departments' => 'item/departments/SuperDeptEditor.php',
        'Departments' => 'item/departments/DepartmentEditor.php',
        'Sub Departments' => 'item/departments/SubDeptEditor.php',
        'divider1' => '__divider__',
        'Manage Likecodes' => 'item/likecodes/',
        'Manage Vendors' => 'item/vendors/',
        'Purchase Orders' => 'purchasing/',
        'Store Coupons' => 'modules/plugins2.0/HouseCoupon/',
    ),
    'Sales Batches' => array(
        'Manage Batches' => '__header__',
        'Sales Batches' => 'batches/newbatch/',
        'Upload Batch' => 'batches/xlsbatch/',
        'Manage Batch Types' => 'batches/BatchTypeEditor.php',
        'Co+op Deals Sales' => 'batches/CAP/',
        'Vendor Pricing' => 'batches/UNFI/',
    ),
    'Reports' => array(
        'All Reports' => 'reports/',
        'Movement Reports' => '__header__',
        'Department Movement' => 'reports/DepartmentMovement/',
        'Brand Movement' => 'reports/ManufacturerMovement/',
        'Item Movement' => 'reports/ProductMovement/',
        'Non-Movement' => 'reports/NonMovement/',
        'Trends' => 'reports/Trends/',
        'Batches' => 'reports/BatchReport/',
        'Sales Reports' => '__header__',
        'General Sales Report' => 'reports/GeneralSales/',
        'Today\'s Sales' => 'reports/SalesToday/',
        'Hourly Sales' => 'reports/HourlySales/HourlySalesReport.php',
        'Hourly Transactions' => 'reports/HourslySales/HourlyTransReport.php',
        'Other Reports' => '__header__',
        'General Day Report' => 'reports/GeneralDay/',
        'Open Rings' => 'reports/OpenRings/',
        'Bad Scans' => 'item/BadScanTool.php',
    ),
    'Membership' => array(
        'View/Edit Members' => 'mem/MemberSearchPage.php',
        'Manage Member Types' => 'mem/MemberTypeEditor.php',
        'Create New Members' => 'mem/NewMemberTool.php',
        'Adjustments &amp; Corrections' => 'mem/MemCorrectionIndex.php',
        'Member Reports' => '__header__',
        'Customer Count' => 'reports/CustomerCount/',
        'Equity Activity' => 'reports/Equity/',
        'AR/Charge Activity' => 'reports/AR/',
        'List Members' => 'reports/Members/',
        'List Purchases' => 'reports/CustomerPurchases/',
        'Discounts Received' => 'reports/Discounts/',
        'Joining &amp; Leaving' => 'reports/OwnerJoinLeave/',
    ),
    'Synchronize' => array(
        'Products' => 'sync/TableSyncPage.php?tablename=products',
        'ProductUser' => 'sync/TableSyncPage.php?tablename=productUser',
        'Membership' => 'sync/TableSyncPage.php?tablename=custdata',
        'Member Cards' => 'sync/TableSyncPage.php?tablename=memberCards',
        'Cashiers' => 'sync/TableSyncPage.php?tablename=employees',
        'Departments' => 'sync/TableSyncPage.php?tablename=departments',
        'Super Departments' => 'sync/TableSyncPage.php?tablename=MasterSuperDepts',
        'Other Data' => 'sync/',
    ),
    'Admin' => array(
        'Cashier Management' => '__header__',
        'Add a new cashier' => 'admin/Cashiers/AddCashierPage.php',
        'View/edit cashiers/' => 'admin/Cashiers/ViewCashiersPage.php',
        'Cashier Performance Report' => 'reports/cash_report/',
        'Special Orders' => '__header__',
        'Create Order' => 'ordering/view.php',
        'Review Active Orders' => 'ordering/clearinghouse.php',
        'Review Old Orders' => 'ordering/historical.php',
        'Receiving Report' => 'ordering/receivingReport.php',
        'divider1' => '__divider__',
        'Tenders' => 'admin/Tenders/',
        'Print Shelftags' => 'admin/labels/',
        'Transaction Lookup' => 'admin/LookupReceipt/',
        'Scheduled Tasks' => 'cron/management/',
        'View Logs' => 'logs/',
        'Import Data' => 'admin/DataImportIndex.php',
    ),
    '__store__' => array(
    ),
);
