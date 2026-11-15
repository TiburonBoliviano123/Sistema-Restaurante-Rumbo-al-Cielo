<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/database.php';
$db=Database::getConnection();
$identificador=trim($_POST['identificador']??'');
if($identificador===''){echo json_encode(['success'=>false,'message'=>'Ingresa tu correo o CI.']);exit;}
$q=$db->prepare('SELECT id,nombre,correo,activo FROM usuarios WHERE correo=? OR ci=? LIMIT 1');$q->execute([$identificador,$identificador]);$u=$q->fetch();
// Respuesta uniforme para no revelar si existe una cuenta.
if($u && (int)$u['activo']===1){$ins=$db->prepare('INSERT INTO solicitudes_recuperacion(identificador) VALUES(?)');$ins->execute([$identificador]);$to='admin@rumboalcielo.bo';$subject='Solicitud de recuperación de acceso';$body='El usuario '.$u['nombre'].' ('.$identificador.') solicitó asistencia para recuperar su acceso. Atienda la solicitud desde la administración.';$headers='From: sistema@rumboalcielo.bo';@mail($to,$subject,$body,$headers);}
echo json_encode(['success'=>true,'message'=>'Si los datos corresponden a una cuenta activa, se notificó al administrador. Solicita asistencia al administrador para restablecer tu acceso.']);
