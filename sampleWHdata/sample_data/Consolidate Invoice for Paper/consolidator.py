import csv
import sys
import os

def fmt_dollar(value):
    """Format a dollar value: skip decimals if whole number, otherwise show 2 decimals."""
    if value == int(value):
        return f"${int(value)}"
    else:
        return f"${value:.2f}"

def consolidate_csv(input_path, output_path):
    if not os.path.exists(input_path):
        print(f"Error: Input file '{input_path}' does not exist.")
        return

    with open(input_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    meta_lines = []
    header = None
    data_rows = []
    footer_row = None

    for line in lines:
        stripped = line.strip()
        if not stripped:
            continue

        # Check for header
        if not header:
            if line.startswith("Type,"):
                header = [x.strip() for x in csv.reader([line]).__next__()]
            else:
                meta_lines.append(line)
        else:
            row = [x.strip() for x in csv.reader([line]).__next__()]
            if not any(row):
                continue
            # Check if this is a footer row (like a total row)
            if row[1].lower() == 'total' or (len(row) > 7 and row[7].lower() == 'qty') or row[0].startswith(','):
                footer_row = row
            elif len(row) > 1 and row[1] == '' and 'total' in ''.join(row).lower():
                footer_row = row
            else:
                data_rows.append(row)

    # Group data rows by (Brand, Model, Condition)
    groups = {}
    for r in data_rows:
        while len(r) < 11:
            r.append('')

        brand = r[1].strip()
        # Normalize brand names
        brand_lower = brand.lower()
        if brand_lower == 'hp':
            brand = 'HP'
        elif brand_lower == 'apple':
            brand = 'Apple'
        elif brand_lower == 'rog':
            brand = 'ROG'
        else:
            brand = brand.capitalize()

        model = r[2].strip()
        model_lower = model.lower()
        if model_lower == "elitebook":
            model = "EliteBook"
        elif model_lower == "probook":
            model = "ProBook"
        elif model_lower == "zbook":
            model = "ZBook"
        elif model_lower == "envy":
            model = "Envy"
        elif model_lower == "spectre":
            model = "Spectre"
        elif model_lower == "gaming":
            model = "Gaming"

        # Determine condition (Untested, Tested, or Parts)
        desc = r[5].lower()
        summary = r[9].lower()
        text_to_search = desc + " " + summary

        if "untested" in text_to_search:
            condition = "Untested"
        elif "parts" in text_to_search:
            condition = "Parts"
        elif "tested" in text_to_search:
            condition = "Tested"
        else:
            condition = "Untested" # fallback

        key = (brand, model, condition)
        if key not in groups:
            groups[key] = []
        groups[key].append(r)

    # For each group, aggregate Series and CPU/Gen, sum QTY, sum Total
    consolidated_blocks = {
        "Untested": [],
        "Tested": [],
        "Parts": []
    }

    grand_total_qty = 0
    grand_total_val = 0.0

    for key, group in groups.items():
        brand, model, condition = key

        # Series aggregation: collect unique non-empty/non-hyphen series
        series_set = []
        for r in group:
            s = r[3].strip()
            if s and s != '-':
                if s not in series_set:
                    series_set.append(s)
        series_str = " | ".join(series_set) if series_set else "-"

        # CPU/Gen aggregation: collect unique non-empty/non-hyphen CPUs
        cpu_set = []
        for r in group:
            c = r[4].strip()
            if c and c != '-':
                if c not in cpu_set:
                    cpu_set.append(c)
        cpu_str = " | ".join(cpu_set) if cpu_set else "-"

        # Sum QTY
        qty_sum = sum(int(r[7]) for r in group if r[7].isdigit())
        grand_total_qty += qty_sum

        # Sum Total Value
        val_sum = 0.0
        for r in group:
            if r[8]:
                val_str = r[8].replace('$', '').replace(',', '').strip()
                try:
                    val_sum += float(val_str)
                except ValueError:
                    pass
        grand_total_val += val_sum

        avg_price = val_sum / qty_sum if qty_sum else 0.0

        new_row = [
            "Laptop",                 # Type
            brand,                    # Brand
            model,                    # Model
            series_str,               # Series
            cpu_str,                  # CPU / Gen
            condition,                # Description
            fmt_dollar(avg_price),    # Price
            str(qty_sum),             # QTY
            fmt_dollar(val_sum),      # Total
            f"Laptop {brand} {model} {condition}", # Summary
            ""                        # Note
        ]

        consolidated_blocks[condition].append(new_row)

    # Sort each block alphabetically by Brand, then Model
    for cond in consolidated_blocks:
        consolidated_blocks[cond].sort(key=lambda x: (x[1].lower(), x[2].lower()))

    # Write to output file
    with open(output_path, 'w', newline='', encoding='utf-8') as f:
        # Write metadata headers
        for ml in meta_lines:
            f.write(ml)

        writer = csv.writer(f)
        if header:
            writer.writerow(header)

        # Write data blocks: Untested, Tested, Parts
        active_blocks = []
        for cond in ["Untested", "Tested", "Parts"]:
            if consolidated_blocks[cond]:
                active_blocks.append(consolidated_blocks[cond])

        for i, block in enumerate(active_blocks):
            for r in block:
                writer.writerow(r)
            # Add blank row between blocks
            if i < len(active_blocks) - 1:
                writer.writerow([])
            elif footer_row:
                # Spacer before footer
                writer.writerow([])

        # Write footer
        if footer_row:
            new_footer = list(footer_row)
            while len(new_footer) < 11:
                new_footer.append('')
            new_footer[7] = str(grand_total_qty)
            new_footer[8] = fmt_dollar(grand_total_val)
            writer.writerow(new_footer)

    print(f"Successfully consolidated '{input_path}' into '{output_path}'.")

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python consolidator.py <input_csv_file> [output_csv_file]")
        input_file = input("Enter input CSV file path: ").strip('"')
        output_file = input("Enter output CSV file path (or press Enter to auto-generate): ").strip('"')
        if not output_file:
            base, ext = os.path.splitext(input_file)
            output_file = f"{base}_consolidated{ext}"
        consolidate_csv(input_file, output_file)
    else:
        input_file = sys.argv[1]
        if len(sys.argv) >= 3:
            output_file = sys.argv[2]
        else:
            base, ext = os.path.splitext(input_file)
            output_file = f"{base}_consolidated{ext}"
        consolidate_csv(input_file, output_file)

    input("\nPress Enter to exit...")
