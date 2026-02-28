<?php
$conn = pg_connect("host=db port=5432 dbname=lockerp user=lockerp password=secret");
if (!$conn) {
    echo "Error: Unable to connect\n";
}
else {
    echo "Connected successfully\n";
    $result = pg_query($conn, "SELECT 1");
    if ($result) {
        echo "Query successful\n";
    }
    else {
        echo "Query failed\n";
    }
}
?>
