<?php
// ✅ Mostrar todos los errores y warnings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php'; // Asegúrate de que aquí se definen $db y las funciones CRUD

echo "<h2>DEMO CRUD QRsume</h2>";

$user_id = 30; // 👈 Usa un ID real si lo tienes

echo "<h3>1️⃣ INSERT test data into 'contactinfo'</h3>";
$insertSuccess = db_insert($db, 'contactinfo', [
    'user_id' => $user_id,
    'email' => 'demo@example.com',
    'phone_number' => '999-999-9999'
]);
echo $insertSuccess ? "Insertado correctamente.<br>" : "Error al insertar.<br>";


echo "<h3>2️⃣ SELECT contactinfo for user_id = $user_id</h3>";
$contacts = db_select($db, 'contactinfo', '*', ['user_id' => $user_id]);
foreach ($contacts as $row) {
    echo "📧 " . htmlspecialchars($row['email']) . " | 📱 " . htmlspecialchars($row['phone_number']) . "<br>";
}


echo "<h3>3️⃣ UPDATE phone number</h3>";
$updateSuccess = db_update($db, 'contactinfo',
    ['phone_number' => '123-456-7890'],
    ['user_id' => $user_id, 'email' => 'demo@example.com']
);
echo $updateSuccess ? "Teléfono actualizado.<br>" : "Error al actualizar.<br>";


echo "<h3>4️⃣ SELECT updated data</h3>";
$updatedContacts = db_select($db, 'contactinfo', '*', ['user_id' => $user_id]);
foreach ($updatedContacts as $row) {
    echo "📧 " . htmlspecialchars($row['email']) . " | 📱 " . htmlspecialchars($row['phone_number']) . "<br>";
}


echo "<h3>5️⃣ DELETE test data</h3>";
$deleteSuccess = db_delete($db, 'contactinfo', [
    'user_id' => $user_id,
    'email' => 'demo@example.com'
]);
echo $deleteSuccess ? "Contacto eliminado.<br>" : "Error al eliminar.<br>";
?>
