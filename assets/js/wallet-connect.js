/**
 * Wallet Connect Integration for DREDD AI
 * Handles MetaMask, WalletConnect, and other wallet connections
 */

class DreddWalletConnect {
    constructor() {
        this.isConnected = false;
        this.walletAddress = null;
        this.chainId = null;
        this.provider = null;
        this.supportedChains = {
            1: 'ethereum',
            56: 'bsc', 
            137: 'polygon',
            369: 'pulsechain',
            42161: 'arbitrum'
        };
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.checkWalletStatus();
    }
    
    bindEvents() {
        // Wallet connection buttons
        $(document).on('click', '.connect-wallet-btn', (e) => this.connectWallet(e));
        $(document).on('click', '.disconnect-wallet-btn', (e) => this.disconnectWallet(e));
        $(document).on('click', '.verify-balance-btn', (e) => this.verifyBalance(e));
        
        // Chain switch buttons
        $(document).on('click', '.switch-chain-btn', (e) => this.switchChain(e));
        
        // Wallet status check
        $(document).on('click', '.check-wallet-status', (e) => this.checkWalletVerification(e));
        
        // Listen for account changes
        if (window.ethereum) {
            window.ethereum.on('accountsChanged', (accounts) => {
                this.handleAccountChange(accounts);
            });
            
            window.ethereum.on('chainChanged', (chainId) => {
                this.handleChainChange(chainId);
            });
        }
    }
    
    async connectWallet(e) {
        e.preventDefault();
        
        const $btn = $(e.currentTarget);
        const originalText = $btn.text();
        
        $btn.text('Connecting...').prop('disabled', true);
        
        try {
            if (!window.ethereum) {
                throw new Error('No wallet detected. Please install MetaMask or another Web3 wallet.');
            }
            
            // Request account access
            const accounts = await window.ethereum.request({
                method: 'eth_requestAccounts'
            });
            
            if (accounts.length === 0) {
                throw new Error('No accounts found. Please unlock your wallet.');
            }
            
            this.walletAddress = accounts[0];
            
            // Get current chain ID
            const chainId = await window.ethereum.request({
                method: 'eth_chainId'
            });
            
            this.chainId = parseInt(chainId, 16);
            
            // Check if chain is supported
            const chainName = this.supportedChains[this.chainId];
            if (!chainName) {
                this.showNotice(`Unsupported network. Please switch to Ethereum, BSC, Polygon, PulseChain, or Arbitrum.`, 'warning');
                return;
            }
            
            this.isConnected = true;
            this.updateWalletUI();
            
            this.showNotice(`Wallet connected: ${this.formatAddress(this.walletAddress)}`, 'success');
            
        } catch (error) {
            console.error('Wallet connection error:', error);
            this.showNotice(`Connection failed: ${error.message}`, 'error');
        } finally {
            $btn.text(originalText).prop('disabled', false);
        }
    }
    
    async disconnectWallet(e) {
        e.preventDefault();
        
        try {
            // Call WordPress disconnect endpoint
            const response = await $.ajax({
                url: dredd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dredd_disconnect_wallet',
                    nonce: dredd_ajax.nonce
                }
            });
            
            if (response.success) {
                this.isConnected = false;
                this.walletAddress = null;
                this.chainId = null;
                this.updateWalletUI();
                
                this.showNotice('Wallet disconnected successfully', 'success');
            } else {
                this.showNotice(`Disconnect failed: ${response.data}`, 'error');
            }
            
        } catch (error) {
            console.error('Wallet disconnect error:', error);
            this.showNotice('Disconnect failed', 'error');
        }
    }
    
    async verifyBalance(e) {
        e.preventDefault();
        
        if (!this.isConnected || !this.walletAddress) {
            this.showNotice('Please connect your wallet first', 'error');
            return;
        }
        
        const $btn = $(e.currentTarget);
        const originalText = $btn.text();
        
        $btn.text('Verifying...').prop('disabled', true);
        
        try {
            const chainName = this.supportedChains[this.chainId];
            
            const response = await $.ajax({
                url: dredd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dredd_verify_wallet_balance',
                    nonce: dredd_ajax.nonce,
                    wallet_address: this.walletAddress,
                    chain: chainName
                }
            });
            
            if (response.success) {
                const data = response.data;
                
                if (data.premium_unlocked) {
                    this.showNotice(data.message, 'success');
                    this.updatePremiumStatus(true, data);
                } else {
                    this.showNotice(data.message, 'warning');
                    this.showBalanceRequirements(data);
                }
                
                this.updateBalanceDisplay(data);
                
            } else {
                this.showNotice(`Verification failed: ${response.data}`, 'error');
            }
            
        } catch (error) {
            console.error('Balance verification error:', error);
            this.showNotice('Verification failed', 'error');
        } finally {
            $btn.text(originalText).prop('disabled', false);
        }
    }
    
    async switchChain(e) {
        e.preventDefault();
        
        const targetChainId = $(e.currentTarget).data('chain-id');
        
        try {
            await window.ethereum.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: `0x${targetChainId.toString(16)}` }]
            });
            
        } catch (switchError) {
            // Chain not added to wallet
            if (switchError.code === 4902) {
                try {
                    await this.addChainToWallet(targetChainId);
                } catch (addError) {
                    console.error('Failed to add chain:', addError);
                    this.showNotice('Failed to add network to wallet', 'error');
                }
            } else {
                console.error('Failed to switch chain:', switchError);
                this.showNotice('Failed to switch network', 'error');
            }
        }
    }
    
    async addChainToWallet(chainId) {
        const chainConfigs = {
            56: {
                chainId: '0x38',
                chainName: 'Binance Smart Chain',
                nativeCurrency: { name: 'BNB', symbol: 'BNB', decimals: 18 },
                rpcUrls: ['https://bsc-dataseed.binance.org/'],
                blockExplorerUrls: ['https://bscscan.com/']
            },
            137: {
                chainId: '0x89',
                chainName: 'Polygon',
                nativeCurrency: { name: 'MATIC', symbol: 'MATIC', decimals: 18 },
                rpcUrls: ['https://polygon-rpc.com/'],
                blockExplorerUrls: ['https://polygonscan.com/']
            },
            369: {
                chainId: '0x171',
                chainName: 'PulseChain',
                nativeCurrency: { name: 'PLS', symbol: 'PLS', decimals: 18 },
                rpcUrls: ['https://rpc.pulsechain.com'],
                blockExplorerUrls: ['https://scan.pulsechain.com/']
            },
            42161: {
                chainId: '0xa4b1',
                chainName: 'Arbitrum One',
                nativeCurrency: { name: 'ETH', symbol: 'ETH', decimals: 18 },
                rpcUrls: ['https://arb1.arbitrum.io/rpc'],
                blockExplorerUrls: ['https://arbiscan.io/']
            }
        };
        
        const config = chainConfigs[chainId];
        if (!config) {
            throw new Error('Unsupported chain configuration');
        }
        
        await window.ethereum.request({
            method: 'wallet_addEthereumChain',
            params: [config]
        });
    }
    
    handleAccountChange(accounts) {
        if (accounts.length === 0) {
            // User disconnected
            this.isConnected = false;
            this.walletAddress = null;
            this.updateWalletUI();
            this.showNotice('Wallet disconnected', 'info');
        } else if (accounts[0] !== this.walletAddress) {
            // User switched accounts
            this.walletAddress = accounts[0];
            this.updateWalletUI();
            this.showNotice(`Switched to account: ${this.formatAddress(this.walletAddress)}`, 'info');
        }
    }
    
    handleChainChange(chainId) {
        this.chainId = parseInt(chainId, 16);
        this.updateWalletUI();
        
        const chainName = this.supportedChains[this.chainId];
        if (chainName) {
            this.showNotice(`Switched to ${chainName.toUpperCase()}`, 'info');
        } else {
            this.showNotice('Switched to unsupported network', 'warning');
        }
    }
    
    async checkWalletStatus() {
        if (!window.ethereum) return;
        
        try {
            const accounts = await window.ethereum.request({
                method: 'eth_accounts'
            });
            
            if (accounts.length > 0) {
                this.walletAddress = accounts[0];
                
                const chainId = await window.ethereum.request({
                    method: 'eth_chainId'
                });
                
                this.chainId = parseInt(chainId, 16);
                this.isConnected = true;
                this.updateWalletUI();
            }
            
        } catch (error) {
            console.error('Wallet status check error:', error);
        }
    }
    
    async checkWalletVerification(e) {
        e.preventDefault();
        
        try {
            const response = await $.ajax({
                url: dredd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dredd_get_wallet_verification_status',
                    nonce: dredd_ajax.nonce
                }
            });
            
            if (response.success) {
                const data = response.data;
                this.updatePremiumStatus(data.premium_access, data);
                this.showNotice(data.message, data.premium_access ? 'success' : 'info');
            } else {
                this.showNotice(`Status check failed: ${response.data}`, 'error');
            }
            
        } catch (error) {
            console.error('Wallet verification status error:', error);
            this.showNotice('Status check failed', 'error');
        }
    }
    
    updateWalletUI() {
        const $connectBtn = $('.connect-wallet-btn');
        const $disconnectBtn = $('.disconnect-wallet-btn');
        const $verifyBtn = $('.verify-balance-btn');
        const $walletStatus = $('.wallet-status');
        const $walletAddress = $('.wallet-address');
        const $walletChain = $('.wallet-chain');
        
        if (this.isConnected) {
            $connectBtn.hide();
            $disconnectBtn.show();
            $verifyBtn.show();
            
            $walletAddress.text(this.formatAddress(this.walletAddress));
            
            const chainName = this.supportedChains[this.chainId];
            $walletChain.text(chainName ? chainName.toUpperCase() : 'Unsupported');
            
            $walletStatus.addClass('connected').removeClass('disconnected');
            
        } else {
            $connectBtn.show();
            $disconnectBtn.hide();
            $verifyBtn.hide();
            
            $walletAddress.text('Not connected');
            $walletChain.text('');
            
            $walletStatus.addClass('disconnected').removeClass('connected');
        }
    }
    
    updatePremiumStatus(hasPremium, data = {}) {
        const $premiumStatus = $('.premium-status');
        const $premiumIndicator = $('.premium-indicator');
        
        if (hasPremium) {
            $premiumStatus.addClass('active').removeClass('inactive');
            $premiumIndicator.text('🟢 Premium Active');
            
            // Enable psycho mode if disabled
            $('.mode-btn[data-mode="psycho"]').removeClass('locked');
            
        } else {
            $premiumStatus.addClass('inactive').removeClass('active');
            $premiumIndicator.text('🔴 Premium Inactive');
        }
    }
    
    updateBalanceDisplay(data) {
        const $balanceEth = $('.balance-eth');
        const $balanceUsd = $('.balance-usd');
        
        if (data.balance_eth !== undefined) {
            $balanceEth.text(`${data.balance_eth.toFixed(4)} ${data.currency || 'ETH'}`);
        }
        
        if (data.balance_usd !== undefined) {
            $balanceUsd.text(`$${data.balance_usd.toFixed(2)}`);
        }
    }
    
    showBalanceRequirements(data) {
        const message = `Minimum balance required:\n` +
                       `• ${data.min_required_eth} ETH (or equivalent)\n` +
                       `• $${data.min_required_usd} USD value\n\n` +
                       `Your current balance:\n` +
                       `• ${data.balance_eth.toFixed(4)} ${data.currency || 'ETH'}\n` +
                       `• $${data.balance_usd.toFixed(2)} USD`;
        
        alert(message);
    }
    
    formatAddress(address) {
        if (!address) return '';
        return `${address.slice(0, 6)}...${address.slice(-4)}`;
    }
    
    showNotice(message, type = 'info') {
        // Create or update notice element
        let $notice = $('.dredd-wallet-notice');
        
        if ($notice.length === 0) {
            $notice = $(`<div class="dredd-wallet-notice"></div>`);
            $('.dredd-chat-wrapper').prepend($notice);
        }
        
        $notice
            .removeClass('success error warning info')
            .addClass(type)
            .text(message)
            .fadeIn();
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $notice.fadeOut();
        }, 5000);
    }
}

// Initialize when document is ready
$(document).ready(() => {
    if (typeof dredd_ajax !== 'undefined') {
        window.dreddWallet = new DreddWalletConnect();
    }
});
