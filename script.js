/**
 * AP Secure E-Voting Portal
 * Core Application Logic (Simulated Frontend for Project purpose)
 */

const app = {
    state: {
        currentPage: 'landing',
        currentVoterId: null,
        selectedCandidateId: null,
        hasVoted: false,
        
        // Simulation Data
        otpCode: '123456', // Simulated OTP for testing
        adminCredentials: { id: 'admin', key: 'apgov2024' }
    },

    // AP Election Simulated Candidate Data
    candidates: [
        { id: '1', name: 'Dr. Srinivas Rao', party: 'Yuvajana Sramika Rythu Congress Party', abbr: 'YSRCP', color: '#10b981', image: 'https://ui-avatars.com/api/?name=Srinivas+Rao&background=10b981&color=fff', logo: 'https://upload.wikimedia.org/wikipedia/commons/e/e1/YSR_Congress_Party_Logo.svg', votes: 1420 },
        { id: '2', name: 'Venkat Naidu', party: 'Telugu Desam Party', abbr: 'TDP', color: '#facc15', image: 'https://ui-avatars.com/api/?name=Venkat+Naidu&background=facc15&color=fff', logo: 'https://upload.wikimedia.org/wikipedia/commons/e/eb/Telugu_Desam_Party_logo.svg', votes: 1350 },
        { id: '3', name: 'P. Kalyan', party: 'Jana Sena Party', abbr: 'JSP', color: '#ef4444', image: 'https://ui-avatars.com/api/?name=P+Kalyan&background=ef4444&color=fff', logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d6/Janasena_Party_Logo.svg/150px-Janasena_Party_Logo.svg.png', votes: 840 },
        { id: '4', name: 'R. Sharma', party: 'Bharatiya Janata Party', abbr: 'BJP', color: '#f97316', image: 'https://ui-avatars.com/api/?name=R+Sharma&background=f97316&color=fff', logo: 'https://upload.wikimedia.org/wikipedia/en/thumb/1/1e/Bharatiya_Janata_Party_logo.svg/100px-Bharatiya_Janata_Party_logo.svg.png', votes: 410 },
        { id: '5', name: 'V. Reddy', party: 'Indian National Congress', abbr: 'INC', color: '#3b82f6', image: 'https://ui-avatars.com/api/?name=V+Reddy&background=3b82f6&color=fff', logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/45/Indian_National_Congress_hand_logo.svg/150px-Indian_National_Congress_hand_logo.svg.png', votes: 310 }
    ],

    // Local Storage Wrapper for Votes
    voterRegistry: JSON.parse(localStorage.getItem('ap_voters')) || {},

    init: function() {
        this.bindEvents();
        this.showPage('landing');
    },

    bindEvents: function() {
        // Mobile Number Input formatting (Numbers only)
        const mobileInput = document.getElementById('mobileNumber');
        if (mobileInput) {
            mobileInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0,10);
            });
        }

        // OTP inputs auto-focus logic
        const otpInputs = document.querySelectorAll('.otp-digit');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                input.value = input.value.replace(/[^0-9]/g, ''); // Numbers only
                if (input.value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
        });
    },

    showPage: function(pageId) {
        document.querySelectorAll('.page-section').forEach(page => {
            page.classList.remove('active');
        });
        document.getElementById(`page-${pageId}`).classList.add('active');
        this.state.currentPage = pageId;

        window.scrollTo({ top: 0, behavior: 'smooth' });

        if (pageId === 'adminDash') this.renderAdminDashboard();
    },

    showToast: function(message, type = 'primary') {
        const toastEl = document.getElementById('appToast');
        const toastBody = document.getElementById('toastMessage');
        toastEl.className = `toast align-items-center text-bg-${type} border-0`;
        toastBody.innerHTML = message;
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
    },

    // --- VOTER AUTHENTICATION FLOW ---

    handleMobileSubmit: async function(e) {
        e.preventDefault();
        const input = document.getElementById('mobileNumber').value;
        
        if (input.length !== 10) {
            this.showToast('Invalid Mobile. Must be 10 digits.', 'danger');
            return;
        }

        // Check if voter already voted
        if (this.voterRegistry[input]) {
            this.showToast('Vote already registered for this mobile number.', 'danger');
            return;
        }

        this.state.currentVoterId = input;
        
        const btn = e.target.querySelector('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Authenticating...';
        btn.disabled = true;

        try {
            const response = await fetch('php/send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mobile: input })
            });
            const result = await response.json();

            btn.innerHTML = originalText;
            btn.disabled = false;

            if (result.status === 'success') {
                const maskedPhone = input.substring(0, 2) + '******' + input.substring(8);
                document.getElementById('maskedPhone').innerText = maskedPhone;
                this.showPage('otp');
                this.showToast(result.message, 'success');
            } else {
                this.showToast(result.message, 'danger');
            }
        } catch (error) {
            // == FALLBACK: If PHP server isn't running (e.g. running from Desktop) ==
            btn.innerHTML = originalText;
            btn.disabled = false;
            console.warn("Fetch failed, activating local simulation mode.");
            
            const maskedPhone = input.substring(0, 2) + '******' + input.substring(8);
            document.getElementById('maskedPhone').innerText = maskedPhone;
            this.showPage('otp');
            this.showToast('Offline Mode Active: Use OTP 123456', 'warning');
        }
    },

    handleOTPSubmit: async function(e) {
        e.preventDefault();
        const inputs = document.querySelectorAll('.otp-digit');
        let enteredOtp = '';
        inputs.forEach(input => enteredOtp += input.value);

        if (enteredOtp.length !== 6) {
            this.showToast('Enter valid 6-digit OTP.', 'warning');
            return;
        }

        const btn = e.target.querySelector('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
        btn.disabled = true;

        try {
            const response = await fetch('php/verify_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    mobile: this.state.currentVoterId, 
                    otp: enteredOtp 
                })
            });
            const result = await response.json();

            btn.innerHTML = originalText;
            btn.disabled = false;

            if (result.status === 'success') {
                document.getElementById('displayVoterId').innerText = 'XXXXXX' + this.state.currentVoterId.slice(-4);
                this.renderCandidates();
                this.showPage('voting');
                this.showToast('Authentication Successful', 'success');
            } else {
                this.showToast(result.message, 'danger');
            }
        } catch (error) {
            // == FALLBACK: If PHP server isn't running ==
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (enteredOtp === '123456') {
                document.getElementById('displayVoterId').innerText = 'XXXXXX' + this.state.currentVoterId.slice(-4);
                this.renderCandidates();
                this.showPage('voting');
                this.showToast('Offline Auth Successful', 'success');
            } else {
                this.showToast('Invalid offline Demo OTP.', 'danger');
            }
        }
    },

    // --- VOTING DASHBOARD ---

    renderCandidates: function() {
        const list = document.getElementById('candidatesList');
        list.innerHTML = '';

        this.candidates.forEach(candidate => {
            const card = document.createElement('div');
            card.className = 'col-lg-10 mb-3';
            card.innerHTML = `
                <div class="candidate-card glass-panel p-3 p-md-4 rounded-4 shadow-sm d-flex flex-column flex-md-row align-items-center justify-content-between">
                    <div class="d-flex align-items-center mb-3 mb-md-0 w-100">
                        <div class="party-logo-wrapper me-4">
                            <!-- Fallback logic for broken external SVG if any -->
                            <img src="${candidate.logo}" class="party-logo" alt="${candidate.abbr}" onerror="this.src='https://ui-avatars.com/api/?name=${candidate.abbr}&background=random&color=fff'">
                        </div>
                        <div class="d-flex align-items-center flex-grow-1">
                            <img src="${candidate.image}" class="candidate-img me-3 d-none d-sm-block" alt="${candidate.name}">
                            <div>
                                <h4 class="fw-bold mb-1">${candidate.name}</h4>
                                <div class="badge text-bg-light border text-dark fs-6">${candidate.party} (${candidate.abbr})</div>
                            </div>
                        </div>
                    </div>
                    <div class="ms-md-4 w-100 text-md-end text-center mt-3 mt-md-0" style="max-width: 200px;">
                        <button class="btn btn-primary w-100 py-2 rounded-3 fw-bold" onclick="app.selectCandidate('${candidate.id}')">
                            <i class="fa-solid fa-check-circle me-1"></i> Vote
                        </button>
                    </div>
                </div>
            `;
            list.appendChild(card);
        });
    },

    selectCandidate: function(id) {
        const candidate = this.candidates.find(c => c.id === id);
        this.state.selectedCandidateId = id;
        document.getElementById('confirmCandidateName').innerText = `${candidate.name} (${candidate.abbr})`;
        
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmVoteModal'));
        confirmModal.show();
    },

    submitVote: function() {
        if(!this.state.selectedCandidateId || !this.state.currentVoterId) return;

        // Hide Modal
        const modalEl = document.getElementById('confirmVoteModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        modalInstance.hide();

        // Register Vote Locally
        this.voterRegistry[this.state.currentVoterId] = {
            candidateId: this.state.selectedCandidateId,
            timestamp: new Date().toISOString()
        };
        localStorage.setItem('ap_voters', JSON.stringify(this.voterRegistry));

        // Increment Candidate Vote Simulation
        const candidate = this.candidates.find(c => c.id === this.state.selectedCandidateId);
        if(candidate) candidate.votes += 1;

        // Show Success Page
        const receipt = 'TRX-' + Math.random().toString(36).substring(2, 10).toUpperCase() + '-' + Date.now().toString().slice(-4);
        document.getElementById('receiptId').innerText = receipt;
        document.getElementById('voteTimestamp').innerText = new Date().toLocaleString();
        
        this.state.hasVoted = true;
        this.showPage('success');
    },

    logout: function() {
        this.state.currentVoterId = null;
        this.state.selectedCandidateId = null;
        this.state.hasVoted = false;
        
        document.getElementById('mobileNumber').value = '';
        document.querySelectorAll('.otp-digit').forEach(i => i.value = '');
        
        this.showPage('landing');
        this.showToast('You have been securely logged out.', 'info');
    },

    // --- ADMIN MODULE ---

    handleAdminLogin: function(e) {
        e.preventDefault();
        const id = document.getElementById('adminId').value;
        const pass = document.getElementById('adminPassword').value;

        if (id === this.state.adminCredentials.id && pass === this.state.adminCredentials.key) {
            this.showPage('adminDash');
            this.showToast('Admin access granted.', 'success');
        } else {
            this.showToast('Invalid admin credentials. Hint: admin / apgov2024', 'danger');
        }
    },

    logoutAdmin: function() {
        document.getElementById('adminId').value = '';
        document.getElementById('adminPassword').value = '';
        this.showPage('landing');
    },

    renderAdminDashboard: function() {
        // Calculate Totals
        let total = 0;
        this.candidates.forEach(c => total += c.votes);
        
        // Add dynamic newly registered votes from local storage
        const localVotesCount = Object.keys(this.voterRegistry).length;
        document.getElementById('totalVotesCount').innerText = (total).toLocaleString();
        document.getElementById('recentVotes').innerText = (localVotesCount + Math.floor(Math.random() * 50)).toLocaleString();

        // Sort candidates
        const sorted = [...this.candidates].sort((a, b) => b.votes - a.votes);

        // Leaderboard List
        const leaderboard = document.getElementById('leaderboardList');
        leaderboard.innerHTML = '';
        
        sorted.forEach((c, index) => {
            const percentage = ((c.votes / total) * 100).toFixed(1);
            const html = `
                <div class="leaderboard-item d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center w-100 mb-2">
                        <div class="d-flex align-items-center">
                            <div class="fs-4 fw-bold text-muted me-3">#${index+1}</div>
                            <div>
                                <h6 class="mb-0 fw-bold">${c.name} <span class="badge text-bg-light ms-1">${c.abbr}</span></h6>
                                <small class="text-muted">${c.votes.toLocaleString()} votes</small>
                            </div>
                        </div>
                        <div class="fw-bold text-end" style="color: ${c.color}">
                            ${percentage}%
                        </div>
                    </div>
                    <div class="progress w-100 progress-thin bg-light">
                        <div class="progress-bar" role="progressbar" style="width: ${percentage}%; background-color: ${c.color}"></div>
                    </div>
                </div>
            `;
            leaderboard.appendChild(document.createRange().createContextualFragment(html));
        });

        // Chart.js Setup
        this.renderChart(sorted);
    },

    renderChart: function(dataArray) {
        const ctx = document.getElementById('resultsChart');
        if (!ctx) return;

        // Destroy existing chart if present to prevent overlap
        if(window.resultsChartInstance) {
            window.resultsChartInstance.destroy();
        }

        const labels = dataArray.map(c => c.abbr);
        const data = dataArray.map(c => c.votes);
        const colors = dataArray.map(c => c.color);

        window.resultsChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Votes Polled',
                    data: data,
                    backgroundColor: colors,
                    borderRadius: 8,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [5, 5] },
                        ticks: { precision: 0 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => {
    app.init();
});
