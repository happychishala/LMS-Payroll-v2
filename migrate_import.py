#!/usr/bin/env python3
"""
migrate_import.py

Reads loan data from a CSV, cleans and parses it, and appends to MySQL `loans` table.

Usage:
  1) Adjust DB_CONFIG & CSV_PATH below.
  2) Ensure your `loans.loan_release_date` column in MySQL allows NULLs,
     or let this script drop rows where it’s missing.
  3) ./migrate_import.py
"""

import pymysql
import pandas as pd
from sqlalchemy import create_engine

# ──── CONFIG ──────────────────────────────────────────────────────────────────

DB_CONFIG = {
    "host":         "127.0.0.1",
    "user":         "root",
    "password":     "",            # ← your MySQL password (or leave blank)
    "db":           "lms",
    "charset":      "utf8mb4",
    "cursorclass":  pymysql.cursors.DictCursor
}

# Path to your CSV file:
CSV_PATH = "/Users/zedfin-it/LMS-Payroll-v2/Book2.csv"

# Which columns in your CSV are numeric (to coerce→float)
NUMERIC_COLS = [
    "Amount",
    "Installment Amount Without Insurance",
    "Total Interest Charged",
    "Total Recoverable",
    "Admin Fee",
    "Arrangement Fee",
    "CRB Fee",
    "Insurance Premium",
    "Monthly Insurance installment",
    "Total Monthly Repayment",
    "Disbursement Amount",
    "Final Disbursement Amount",
    "Withholding Amount",
    "Outstanding balance",
    "Zedfin Balance",
]

# Which columns are dates (to coerce→python date or None)
DATE_COLS = [
    "Loan Issue Date",      # → loan_release_date
    "First Repayment Date", # → first_repayment_date
    "Maturity Date",        # → maturity_date / loan_due_date
    "DOB",                  # → date_of_birth
]

# ──── MAIN ────────────────────────────────────────────────────────────────────

def main():
    # 1) Create SQLAlchemy engine + PyMySQL connection
    engine = create_engine(
        f"mysql+pymysql://{DB_CONFIG['user']}:{DB_CONFIG['password']}"
        f"@{DB_CONFIG['host']}/{DB_CONFIG['db']}?charset={DB_CONFIG['charset']}",
        echo=False
    )
    conn = pymysql.connect(**DB_CONFIG)

    try:
        # 2) Fetch the two payroll-type IDs just once
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM loan_types WHERE loan_name=%s",
                ("Payroll-CNMC",)
            )
            cnmc_id = cur.fetchone()["id"]
            cur.execute(
                "SELECT id FROM loan_types WHERE loan_name=%s",
                ("Payroll-GRZ",)
            )
            grz_id = cur.fetchone()["id"]

        # 3) Load CSV (all columns as strings)
        df = pd.read_csv(CSV_PATH, dtype=str)
        df.columns = df.columns.str.strip()  # trim whitespace

        # 4) Map employer → loan_type_id
        df["loan_type_id"] = df["Employer"].apply(
            lambda s: cnmc_id if "CNMC" in (s or "") else grz_id
        )

        # 5) Numeric columns → float (fill missing→0)
        for col in NUMERIC_COLS:
            if col in df:
                df[col] = pd.to_numeric(df[col], errors="coerce").fillna(0)

        # 6) Date columns → python date (NaT→None)
        for col in DATE_COLS:
            if col in df:
                df[col] = pd.to_datetime(df[col], errors="coerce").dt.date

        # 7) Build the DataFrame that matches your `loans` schema
        out = pd.DataFrame({
            "borrower_id":                   df["Client ID"],
            "loan_id":                       df["Loan ID"],
            "loan_number":                   df["Loan ID"],
            "loan_type_id":                  df["loan_type_id"],
            "loan_category":                 df["Loan Category"],
            "loan_status":                   df["Status"],
            "principal_amount":              df["Amount"],
            "loan_release_date":             df["Loan Issue Date"],
            "loan_duration":                 df["Term (Months)"],
            "duration_period":               "Months",
            "repayment_amount":              df["Installment Amount Without Insurance"],
            "loan_due_date":                 df["Maturity Date"],
            "activate_loan_agreement_form":  0,
            "interest_rate":                 df["Annual Interest Rate (%)"],
            "interest_amount":               df["Total Interest Charged"],
            "total_interest_charged":        df["Total Interest Charged"],
            "total_recoverable":             df["Total Recoverable"],
            "admin_fee":                     df["Admin Fee"],
            "arrangement_fee":               df["Arrangement Fee"],
            "crb_fee":                       df["CRB Fee"],
            "insurance_fee":                 df["Insurance Premium"],
            "monthly_insurance":             df["Monthly Insurance installment"],
            "total_monthly_repayment":       df["Total Monthly Repayment"],
            "disbursement_amount":           df["Disbursement Amount"],
            "final_disbursement_amount":     df["Final Disbursement Amount"],
            "withholding_amount":            df["Withholding Amount"],
            "balance":                       df["Outstanding balance"],
            "zedfin_balance":                df["Zedfin Balance"],
            "employee_no":                   df["Employee No."],
            "employer":                      df["Employer"],
            "other_names":                   df["Other Names"],
            "last_name":                     df["Last Name"],
            "third_party_name":              df.get("Third party Name", None),
            "third_party_balance":           df.get("Third Party Balance", None),
            "third_party_name_2":            df.get("Third party Name 2", None),
            "third_party_balance_2":         df.get("Third Party Balance 2", None),
            "third_party_name_3":            df.get("Third party Name 3", None),
            "third_party_balance_3":         df.get("Third Party Balance 3", None),
            "total_third_party_balance":     df.get("Total Third Party Balance", 0),
            "term_months":                   df["Term (Months)"],
            "installment_without_insurance": df["Installment Amount Without Insurance"],
            "first_repayment_date":          df["First Repayment Date"],
            "maturity_date":                 df["Maturity Date"],
            "date_of_birth":                 df["DOB"],
            "nrc":                           df["NRC"],
            "gender":                        df["Gender"],
            "created_at":                    pd.Timestamp.now(),
            "updated_at":                    pd.Timestamp.now(),
        })

        # 8) Drop rows where loan_release_date is still null
        before = len(out)
        out = out.dropna(subset=["loan_release_date"])
        dropped = before - len(out)
        if dropped:
            print(f"⚠️ Dropped {dropped} rows missing `loan_release_date`")

        # 9) Append into MySQL in small batches
        out.to_sql(
            name="loans",
            con=engine,
            if_exists="append",
            index=False,
            method="multi",
            chunksize=50,
        )

        print(f"✅ Inserted {len(out)} rows into `loans`.")

    finally:
        conn.close()


if __name__ == "__main__":
    main()
