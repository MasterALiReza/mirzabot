document.addEventListener("DOMContentLoaded", () => {
    const telegramBoard = document.getElementById("telegram-board");
    const unusedKeysContainer = document.getElementById("unused-keys");
    const saveBtn = document.getElementById("save-keyboard-btn");
    
    // Load data from global variable injected by PHP
    const data = window.KEYBOARD_INITIAL_DATA || { keylist: [], userlist: [], text: {} };
    const textDict = data.text;
    
    let rowSortables = [];

    // Initialize layout
    renderUnusedKeys(data.keylist);
    renderActiveKeyboard(data.userlist);
    ensureEmptyRowAtBottom();
    initSortables();

    function createButtonElement(keyName) {
        const btn = document.createElement("div");
        btn.className = "kb-btn telegram-btn";
        btn.dataset.key = keyName;
        // The span helps with text overflow
        btn.innerHTML = `<span>${textDict[keyName] || keyName}</span>`;
        return btn;
    }

    function createRowElement() {
        const row = document.createElement("div");
        row.className = "telegram-row";
        return row;
    }

    function renderUnusedKeys(keylist) {
        unusedKeysContainer.innerHTML = "";
        keylist.forEach(item => {
            if (item && item.length > 0 && item[0].text) {
                unusedKeysContainer.appendChild(createButtonElement(item[0].text));
            }
        });
    }

    function renderActiveKeyboard(userlist) {
        telegramBoard.innerHTML = "";
        userlist.forEach(rowArr => {
            const rowEl = createRowElement();
            let hasItems = false;
            rowArr.forEach(item => {
                if (item.text) {
                    rowEl.appendChild(createButtonElement(item.text));
                    hasItems = true;
                }
            });
            if (hasItems) {
                telegramBoard.appendChild(rowEl);
            }
        });
    }

    function ensureEmptyRowAtBottom() {
        // Check if the last row is empty
        const rows = telegramBoard.querySelectorAll(".telegram-row");
        let lastRow = rows.length > 0 ? rows[rows.length - 1] : null;
        
        // Remove empty rows that are NOT the last row
        rows.forEach(row => {
            if (row.children.length === 0 && row !== lastRow) {
                if (rowSortables.includes(row.sortableInstance)) {
                    // Cleanup Sortable instance
                    row.sortableInstance.destroy();
                    rowSortables = rowSortables.filter(s => s !== row.sortableInstance);
                }
                row.remove();
            }
        });

        // Re-evaluate last row
        const updatedRows = telegramBoard.querySelectorAll(".telegram-row");
        lastRow = updatedRows.length > 0 ? updatedRows[updatedRows.length - 1] : null;

        if (!lastRow || lastRow.children.length > 0) {
            // Last row has items, create a new empty row
            const newEmptyRow = createRowElement();
            newEmptyRow.classList.add("empty-row");
            telegramBoard.appendChild(newEmptyRow);
            initRowSortable(newEmptyRow);
        } else {
            // Last row is already empty
            lastRow.classList.add("empty-row");
        }

        // Clean up empty-row class from non-empty rows
        telegramBoard.querySelectorAll(".telegram-row").forEach(row => {
            if (row.children.length > 0) {
                row.classList.remove("empty-row");
            }
        });
    }

    function initSortables() {
        // Sortable for unused keys area
        new Sortable(unusedKeysContainer, {
            group: "shared",
            animation: 150,
            ghostClass: "sortable-ghost",
            onEnd: ensureEmptyRowAtBottom
        });

        // Initialize sortable for existing rows
        document.querySelectorAll(".telegram-row").forEach(initRowSortable);
    }

    function initRowSortable(rowElement) {
        const sortable = new Sortable(rowElement, {
            group: "shared",
            animation: 150,
            ghostClass: "sortable-ghost",
            onEnd: ensureEmptyRowAtBottom
        });
        rowElement.sortableInstance = sortable;
        rowSortables.push(sortable);
    }

    saveBtn.addEventListener("click", async () => {
        saveBtn.innerText = "در حال ذخیره...";
        saveBtn.disabled = true;

        const keyboardData = [];
        
        document.querySelectorAll(".telegram-row").forEach(row => {
            const rowData = [];
            Array.from(row.children).forEach(btn => {
                if (btn.dataset.key) {
                    rowData.push({ text: btn.dataset.key });
                }
            });
            // Only add non-empty rows
            if (rowData.length > 0) {
                keyboardData.push(rowData);
            }
        });

        try {
            const response = await fetch("", { // Post to same page (keyboard.php)
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(keyboardData)
            });
            
            if (response.ok) {
                // Flash success
                saveBtn.style.background = "#059669";
                saveBtn.innerText = "ذخیره شد!";
                setTimeout(() => {
                    saveBtn.style.background = "";
                    saveBtn.innerText = "ذخیره تغییرات";
                    saveBtn.disabled = false;
                }, 2000);
            } else {
                throw new Error("Server response not OK");
            }
        } catch (error) {
            console.error(error);
            alert("خطا در ذخیره‌سازی");
            saveBtn.innerText = "ذخیره تغییرات";
            saveBtn.disabled = false;
        }
    });
});
