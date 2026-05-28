import pandas as pd

EXCEL_FILE = "/home/dmitry/Downloads/Все звонки.xlsx"

def main():
    df = pd.read_excel(EXCEL_FILE)
    print(f"Total calls in Excel: {len(df)}")
    
    # Stats: Incoming, Outgoing, Missed
    # 'Инициатор', 'Вызываемый', 'Принял', 'Длительность', 'Ожидание'
    print("\nColumns:", df.columns.tolist())
    
    print("\nFirst few rows:")
    print(df.head())
    
    # Maybe group by status or something?
    # Usually A1 CDR has call direction or duration = 0 for missed.
    
if __name__ == "__main__":
    main()
