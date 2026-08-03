import pandas as pd
import re

df = pd.read_excel('Reporte_Items_2026-08-01 12_28_05.xlsx', header=2)

cat_map = {
    'Buzo Educ.Física': 1,
    'Camipolo': 3,
    'Camisa unisex': 4,
    'Casaca Educ.Física': 5,
    'Corbata': 6,
    'Falda': 9,
    'Pantaloneta Educ.Física': 10,
    'Pantalón Vestir': 11,
    'Polo Educ.Física': 12,
    'Short Educ.Física': 13,
    'Short Inicial': 14,
}

sql = "USE puntonet_pos;\n"
sql += "SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE insumos; SET FOREIGN_KEY_CHECKS=1;\n"

def process_item(desc, price):
    if pd.isna(desc) or desc == 'VARIOUS_ITEM':
        return None
    
    desc = str(desc).strip()
    price = float(price) if not pd.isna(price) else 0.0

    id_cat = 2 # General by default
    id_talla = 'NULL'
    name = desc # KEEP THE ORIGINAL NAME WITH THE SIZE

    # Uniforms categorization based on name prefix
    matched_cat = False
    for cat_name, cat_id in cat_map.items():
        if desc.startswith(cat_name):
            id_cat = cat_id
            matched_cat = True
            break
            
    if not matched_cat:
        # Modulos rules
        if 'Sec' in desc:
            id_cat = 17
        elif any(c in desc for c in ['1° Prim', '2° Prim', '3° Prim']):
            id_cat = 15
        elif any(c in desc for c in ['4° Prim', '5° Prim', '6° Prim']):
            id_cat = 16

    return f"INSERT INTO insumos (id_categoria, id_unidad, nombre, precio_unitario, costo_produccion, stock_piezas, estado, id_talla) VALUES ({id_cat}, 1, '{name}', {price}, 0, 0, 1, {id_talla});"

for idx, row in df.iterrows():
    desc = row['Descripción']
    price = row['Precio']
    
    stmt = process_item(desc, price)
    if stmt:
        sql += stmt + "\n"

with open('import_items.sql', 'w', encoding='utf-8') as f:
    f.write(sql)

print("SQL script created successfully with original names.")
