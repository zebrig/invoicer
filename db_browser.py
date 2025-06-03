#!/usr/bin/env python3
"""
Lightweight curses-based SQLite database browser for viewing, editing, and deleting records.

Usage:
    python3 db_browser.py [path/to/database.db]

If no path is provided, the DB_FILE constant from config.php is used.

Controls:
  In table list:
    Up/Down  - navigate tables
    Enter    - select/enter table
    q        - quit

  In record view:
    Up/Down  - navigate records
    e        - edit selected record (opens $EDITOR or vi)
    d        - delete selected record
    r        - reload records
    b or q   - back to table list

"""
import curses
import json
import os
import sqlite3
import subprocess
import shutil
import sys
import tempfile


def main(stdscr, db_path):
    conn = sqlite3.connect(db_path)
    # allow non-UTF-8 text by replacing invalid bytes, so viewer doesn't crash on malformed data
    conn.text_factory = lambda b: b.decode('utf-8', 'replace')
    conn.row_factory = sqlite3.Row
    curses.curs_set(0)
    while True:
        table = table_menu(stdscr, conn)
        if table is None:
            break
        view_table(stdscr, conn, table)


def table_menu(stdscr, conn):
    cursor = conn.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
    tables = [row['name'] for row in cursor]
    if not tables:
        return None
    idx = 0
    while True:
        stdscr.clear()
        h, w = stdscr.getmaxyx()
        stdscr.addstr(0, 0, "Tables:")
        for i, name in enumerate(tables):
            y = i + 1
            if y >= h - 1:
                break
            attr = curses.A_REVERSE if i == idx else curses.A_NORMAL
            stdscr.addstr(y, 2, name[:w-4], attr)
        stdscr.addstr(h-1, 0, "Up/Down: Navigate  Enter: Select  q: Quit")
        stdscr.refresh()
        key = stdscr.getch()
        if key in (curses.KEY_DOWN, ord('j')):
            idx = min(idx+1, len(tables)-1)
        elif key in (curses.KEY_UP, ord('k')):
            idx = max(idx-1, 0)
        elif key in (ord('q'), 27):
            return None
        elif key in (curses.KEY_ENTER, 10, 13):
            return tables[idx]


def view_table(stdscr, conn, table):
    def load_rows():
        data = conn.execute(f"SELECT rowid AS __rowid__, * FROM '{table}'").fetchall()
        if data:
            cols = data[0].keys()[1:]
        else:
            cols = [col[1] for col in conn.execute(f"PRAGMA table_info('{table}')")]
        rowids = [row['__rowid__'] for row in data]
        rows = [tuple(row)[1:] for row in data]
        return cols, rows, rowids

    cols, rows, rowids = load_rows()
    idx = 0
    start = 0
    while True:
        stdscr.clear()
        h, w = stdscr.getmaxyx()
        title = f"Table: {table} ({len(rows)} rows)"
        stdscr.addstr(0, 0, title[:w])
        hdr = ' | '.join(cols)
        stdscr.addstr(1, 0, hdr[:w], curses.A_UNDERLINE)
        visible = h - 4
        for i in range(visible):
            ridx = start + i
            if ridx >= len(rows):
                break
            row = rows[ridx]
            line = ' | '.join(str(x) for x in row)
            attr = curses.A_REVERSE if ridx == idx else curses.A_NORMAL
            stdscr.addstr(i+2, 0, line[:w], attr)
        stdscr.addstr(h-1, 0, "Up/Down: Navigate  e: Edit  d: Delete  r: Reload  b/q: Back")
        stdscr.refresh()
        key = stdscr.getch()
        if key in (curses.KEY_DOWN, ord('j')):
            if idx < len(rows)-1:
                idx += 1
                if idx >= start + visible:
                    start += 1
        elif key in (curses.KEY_UP, ord('k')):
            if idx > 0:
                idx -= 1
                if idx < start:
                    start -= 1
        elif key == ord('r'):
            cols, rows, rowids = load_rows()
            idx = start = 0
        elif key in (ord('b'), ord('q'), 27):
            break
        elif key == ord('d') and rows:
            if confirm(stdscr, f"Delete row {idx+1}/{len(rows)}? (y/N)"):
                conn.execute(f"DELETE FROM '{table}' WHERE rowid=?", (rowids[idx],))
                conn.commit()
                cols, rows, rowids = load_rows()
                idx = start = 0
        elif key == ord('e') and rows:
            edit_row(conn, table, cols, rows[idx], rowids[idx])
            cols, rows, rowids = load_rows()


def confirm(stdscr, message):
    h, w = stdscr.getmaxyx()
    stdscr.addstr(h-2, 0, message[:w])
    stdscr.clrtoeol()
    stdscr.refresh()
    key = stdscr.getch()
    return key in (ord('y'), ord('Y'))


def edit_row(conn, table, cols, row, rowid):
    data = {col: row[i] for i, col in enumerate(cols)}
    with tempfile.NamedTemporaryFile('w+', delete=False, suffix='.json') as tf:
        tf.write(json.dumps(data, indent=2, ensure_ascii=False))
        tf.flush()

        # Suspend curses before calling external editor
        curses.endwin()

        editor = os.environ.get('EDITOR', 'vi')
        subprocess.call([editor, tf.name])

        # Resume curses after editing
        stdscr = curses.initscr()
        curses.noecho()
        curses.cbreak()
        stdscr.keypad(True)
        curses.curs_set(0)
        curses.flushinp()  # Flush any editor leftovers

        tf.seek(0)
        try:
            newdata = json.load(tf)
        except Exception:
            return
    os.unlink(tf.name)
    keys = [k for k in cols if k in newdata]
    vals = [newdata[k] for k in keys]
    set_clause = ', '.join(f"{k}=?" for k in keys)
    sql = f"UPDATE '{table}' SET {set_clause} WHERE rowid=?"
    conn.execute(sql, vals + [rowid])
    conn.commit()



if __name__ == '__main__':
    if len(sys.argv) > 1:
        db = sys.argv[1]
    else:
        script_dir = os.path.abspath(os.path.dirname(__file__))
        if not shutil.which('php'):
            print("Error: php CLI is required to autodetect database file", file=sys.stderr)
            sys.exit(1)
        try:
            db = subprocess.check_output(
                ['php', '-r', "require 'config.php'; echo DB_FILE;"],
                cwd=script_dir
            ).decode().strip()
        except subprocess.CalledProcessError:
            print("Error: failed to load DB_FILE from config.php", file=sys.stderr)
            sys.exit(1)

    if not os.path.exists(db):
        print(f"Database file not found: {db}", file=sys.stderr)
        sys.exit(1)

    curses.wrapper(main, db)
