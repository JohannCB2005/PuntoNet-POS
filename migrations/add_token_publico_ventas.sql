-- Comprobantes públicos por enlace (estilo tukifac)
-- Agrega token_publico a ventas para poder compartir el comprobante por URL
-- (validado con hash_equals en C_ComprobantePublico, patrón de pedidos_online).
ALTER TABLE ventas
    ADD COLUMN token_publico CHAR(32) NOT NULL DEFAULT '' COMMENT 'Token para enlace público del comprobante (bin2hex(random_bytes(16)))' AFTER serie;

CREATE INDEX idx_ventas_token_publico ON ventas (token_publico);