INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'correo_corporativo', correo_corporativo, 'Correo usado para notificaciones' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_host', smtp_host, 'Host SMTP para envío de correos' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_user', smtp_user, 'Usuario SMTP' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_pass', smtp_pass, 'Contraseña SMTP' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_port', smtp_port, 'Puerto SMTP' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_secure', smtp_secure, 'Tipo de seguridad SMTP' FROM configuracion WHERE id=1;
