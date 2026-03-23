ALTER TABLE pedidos
ADD COLUMN recargas_api_pedido_id VARCHAR(120) NULL AFTER ff_api_payload,
ADD COLUMN recargas_api_estado VARCHAR(40) NULL AFTER recargas_api_pedido_id,
ADD COLUMN recargas_api_codigo_entregado LONGTEXT NULL AFTER recargas_api_estado,
ADD COLUMN recargas_api_reembolso DECIMAL(12,2) NULL AFTER recargas_api_codigo_entregado,
ADD COLUMN recargas_api_ultimo_check DATETIME NULL AFTER recargas_api_reembolso;
