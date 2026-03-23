ALTER TABLE pedidos
ADD COLUMN player_fields_json LONGTEXT NULL AFTER user_identifier;
