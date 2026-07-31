<!-- ===== NSSF Assistant Chatbot Widget ===== -->
<!-- Include this file right before </body> on any page, using: -->
<!-- PHP include statement (see README) -->

<div id="chatbotBubble" onclick="toggleChatbot()">💬</div>

<div id="chatbotWindow">
    <div id="chatbotHeader">
        <span>NSSF Assistant</span>
        <button onclick="toggleChatbot()" id="chatbotCloseBtn">&times;</button>
    </div>
    <div id="chatbotMessages"></div>
    <div id="chatbotInputRow">
        <input type="text" id="chatbotInput" placeholder="Ask a question..." onkeydown="if(event.key==='Enter'){sendChatbotMessage();}">
        <button onclick="sendChatbotMessage()">Send</button>
    </div>
</div>

<script>
(function () {
    // ---- Knowledge base: each entry has keywords to match and a response ----
    const chatbotFAQ = [
        {
            keywords: ["trn", "transaction reference", "generate trn", "payroll"],
            response: "A TRN (Transaction Reference Number) is generated automatically for a batch of contributions. Go to Overview and click 'Generate TRN', or it may auto-generate itself when you first log in each month, based on employees' stored salaries."
        },
        {
            keywords: ["penalty", "overdue", "late payment", "fine"],
            response: "A late payment penalty of 5% per month is applied automatically once a contribution is marked 'Overdue' and past its due date (the 15th of the following month)."
        },
        {
            keywords: ["add employee", "new employee", "register employee"],
            response: "Go to the Employees panel and click '+ Add New Employee'. Make sure to set their Monthly Salary too - it's needed for automatic TRN/payroll calculations."
        },
        {
            keywords: ["salary", "monthly salary", "set salary"],
            response: "Right-click an employee's row in the Employees panel and choose Edit to set or update their Monthly Salary."
        },
        {
            keywords: ["contribution", "5%", "10%", "percentage", "how much", "deduction"],
            response: "Employees contribute 5% of their gross salary, and employers contribute an additional 10%, for a total of 15% per contribution."
        },
        {
            keywords: ["receipt", "proof of payment", "upload"],
            response: "You can attach a receipt (PDF, JPG, or PNG) when adding or editing a contribution, under 'Proof of Payment'. It will show as a 'View' link once uploaded."
        },
        {
            keywords: ["export", "csv", "download", "excel"],
            response: "Use the 'Export to CSV' button on the Contributions panel - it downloads whatever is currently shown, respecting any filters you've applied."
        },
        {
            keywords: ["password", "reset password", "forgot password", "change password"],
            response: "Click 'Forgot your password?' on the login page and enter your email. A reset link will be sent to you."
        },
        {
            keywords: ["compliance", "compliance rate"],
            response: "Compliance Rate is the percentage of your contribution records that are marked 'Paid' out of the total recorded. You can see it as a progress bar on the Overview panel."
        },
        {
            keywords: ["map", "location", "directions", "address"],
            response: "On the Company Profile panel, you'll find an embedded map and a 'Get Directions' button based on the registered company address."
        },
        {
            keywords: ["statement", "print", "pdf"],
            response: "Employees can go to My Contributions and click 'View / Print Statement' to see a printable summary, with an option to filter by year."
        },
        {
            keywords: ["email", "contact", "message employer"],
            response: "Admins can email an employer directly from the Employers panel by clicking 'Email' next to their name - the address is pulled automatically from their account."
        },
        {
            keywords: ["hello", "hi", "hey"],
            response: "Hello! I'm the NSSF Assistant. Ask me about TRNs, penalties, contributions, uploads, or anything else about using this system."
        },
        {
            keywords: ["thank", "thanks"],
            response: "You're welcome! Let me know if you have any other questions."
        }
    ];

    const fallbackResponse = "I'm not sure about that one. Try asking about: TRNs, penalties, adding employees, salaries, receipts, CSV export, password reset, or compliance rate.";

    function findBestResponse(userText) {
        const text = userText.toLowerCase();
        let bestMatch = null;
        let bestScore = 0;

        chatbotFAQ.forEach(function (entry) {
            let score = 0;
            entry.keywords.forEach(function (kw) {
                if (text.includes(kw)) score++;
            });
            if (score > bestScore) {
                bestScore = score;
                bestMatch = entry;
            }
        });

        return bestMatch ? bestMatch.response : fallbackResponse;
    }

    function appendMessage(text, sender) {
        const messagesDiv = document.getElementById('chatbotMessages');
        const bubble = document.createElement('div');
        bubble.className = 'chatbot-msg ' + sender;
        bubble.textContent = text;
        messagesDiv.appendChild(bubble);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    window.toggleChatbot = function () {
        const win = document.getElementById('chatbotWindow');
        const isOpen = win.style.display === 'flex';
        win.style.display = isOpen ? 'none' : 'flex';
        if (!isOpen && document.getElementById('chatbotMessages').children.length === 0) {
            appendMessage("Hi! I'm the NSSF Assistant. Ask me anything about using this system.", 'bot');
        }
    };

    window.sendChatbotMessage = function () {
        const input = document.getElementById('chatbotInput');
        const text = input.value.trim();
        if (text === '') return;

        appendMessage(text, 'user');
        input.value = '';

        setTimeout(function () {
            appendMessage(findBestResponse(text), 'bot');
        }, 300); // tiny delay so it feels like a real reply, not instant
    };
})();
</script>