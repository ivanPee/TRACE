import React, { useMemo, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import StatGrid from '../../components/StatGrid';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

const money = (value) => (Number.isFinite(Number(value)) ? `PHP ${Number(value).toFixed(2)}` : 'Recorded');

const formatDate = (value) => {
  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value || 'Date pending';
  }

  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const statusTone = (status) => {
  const normalized = String(status || '').toLowerCase();

  if (['paid', 'completed', 'approved'].includes(normalized)) {
    return 'success';
  }

  if (normalized === 'cancelled') {
    return 'danger';
  }

  return 'warning';
};

export default function TransactionsScreen({ navigation }) {
  const { currentRole, transactions, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const safeTransactions = useMemo(() => (Array.isArray(transactions) ? transactions : []), [transactions]);
  const totals = useMemo(() => safeTransactions.reduce((summary, transaction) => {
    const amount = Number(transaction.amount);

    if (transaction.type === 'payment' && Number.isFinite(amount)) {
      summary.paid += amount;
    }

    if (transaction.type === 'refund' && Number.isFinite(amount)) {
      summary.refunded += amount;
    }

    if (transaction.type === 'payout' && Number.isFinite(amount)) {
      summary.earned += amount;
    }

    if (['paid', 'completed'].includes(String(transaction.status || '').toLowerCase())) {
      summary.completed += 1;
    }

    return summary;
  }, { paid: 0, earned: 0, refunded: 0, completed: 0 }), [safeTransactions]);

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="transactions" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Transactions"
        title="Saved transaction history."
        subtitle="Payments, driver payouts, and completed trip records are kept here for review."
      />

      <StatGrid
        items={[
          { label: 'Records', value: safeTransactions.length, icon: 'receipt' },
          { label: currentRole === 'driver' ? 'Earned' : 'Paid', value: money(currentRole === 'driver' ? totals.earned : totals.paid), icon: currentRole === 'driver' ? 'coins' : 'wallet' },
          { label: 'Refunds', value: money(totals.refunded), icon: 'undo' },
          { label: 'Complete', value: totals.completed, icon: 'check-circle' },
        ]}
      />

      {!safeTransactions.length ? (
        <SectionCard title="No transactions yet" icon="receipt">
          <Text style={styles.emptyText}>Saved payments, payouts, and completed trip records will appear here after activity is recorded.</Text>
        </SectionCard>
      ) : null}

      {safeTransactions.map((transaction) => (
        <SectionCard key={transaction.id} title={transaction.title || 'Transaction'} subtitle={formatDate(transaction.date)} icon={transaction.type === 'payout' ? 'coins' : transaction.type === 'refund' ? 'undo' : transaction.type === 'payment' ? 'wallet' : 'route'}>
          <View style={styles.transactionHeader}>
            <Pill label={transaction.status || 'recorded'} tone={statusTone(transaction.status)} />
            <Text style={styles.amount}>{money(transaction.amount)}</Text>
          </View>
          <Text style={styles.description}>{transaction.description || 'TRACE transport transaction'}</Text>
          <InfoRow icon="hashtag" label="Reference" value={transaction.reference || transaction.id} />
          <InfoRow icon="credit-card" label="Method" value={transaction.method || transaction.type || 'record'} />
          {transaction.billingMonth ? <InfoRow icon="calendar-alt" label="Billing month" value={transaction.billingMonth} /> : null}
          {transaction.scheduledDate ? <InfoRow icon="calendar-day" label="Trip date" value={`${transaction.scheduledDate}${transaction.scheduledTime ? ` / ${transaction.scheduledTime}` : ''}`} /> : null}
        </SectionCard>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  amount: {
    color: colors.deep,
    fontSize: 18,
    fontWeight: '900',
  },
  description: {
    color: colors.slate,
    fontSize: 13,
    lineHeight: 19,
    marginBottom: 10,
  },
  emptyText: {
    color: colors.slate,
    lineHeight: 20,
  },
  transactionHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
});
