export default class Mediator {
    constructor() {
      this.channels = new Map();
      this.channels.set("*", new Map()); // Initialize the "*" channel by default
    }
  
    /**
     * Adds an observer to a channel for a specific sender.
     * If no channel is provided, subscribes to the "*" channel.
     * @param {Object} sender - The sender this observer is interested in.
     * @param {Function} observer - The observer function to be notified.
     * @param {string} [channel="*"] - The channel to subscribe to.
     */
    addObserver(sender, observer, channel = "*") {
      if (!this.channels.has(channel)) {
        this.channels.set(channel, new Map());
      }
      const channelMap = this.channels.get(channel);
      if (!channelMap.has(sender)) {
        channelMap.set(sender, new Set());
      }
      channelMap.get(sender).add(observer);
    }
  
    /**
     * Removes an observer from a channel for a specific sender.
     * If no channel is provided, unsubscribes from the "*" channel.
     * @param {Object} sender - The sender this observer is interested in.
     * @param {Function} observer - The observer function to remove.
     * @param {string} [channel="*"] - The channel to unsubscribe from.
     */
    removeObserver(sender, observer, channel = "*") {
      if (this.channels.has(channel)) {
        const channelMap = this.channels.get(channel);
        if (channelMap.has(sender)) {
          channelMap.get(sender).delete(observer);
          if (channelMap.get(sender).size === 0) {
            channelMap.delete(sender);
          }
        }
      }
    }
  
    /**
     * Sends a notification to observers of a specific channel and sender.
     * Also notifies observers of the "*" channel, regardless of the specific channel.
     * @param {Object} sender - The sender of the event.
     * @param {string} channel - The channel to send notifications on.
     * @param {...any} data - The data to pass to the observers.
     */
    notify(sender, channel, ...data) {
      // Notify observers of the specific channel
      if (this.channels.has(channel)) {
        const channelMap = this.channels.get(channel);
        if (channelMap.has(sender)) {
          channelMap.get(sender).forEach(observer => observer(...data));
        }
      }
  
      // Always notify observers of the "*" channel
      const globalChannelMap = this.channels.get("*");
      if (globalChannelMap.has(sender)) {
        globalChannelMap.get(sender).forEach(observer => observer(...data));
      }
    }
  }
  