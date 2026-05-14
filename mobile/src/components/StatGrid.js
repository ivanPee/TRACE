import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import { colors } from '../theme/colors';

export default function StatGrid({ items }) {
  return (
    <View style={styles.grid}>
      {items.map((item) => (
        <View key={item.label} style={styles.card}>
          <View style={styles.surface}>
            <View style={styles.topLine}>
              <Text style={styles.value}>{item.value}</Text>
              {item.icon ? (
                <View style={styles.iconWrap}>
                  <FontAwesome5 name={item.icon} size={14} solid color={colors.accent} />
                </View>
              ) : null}
            </View>
            <Text style={styles.label}>{item.label}</Text>
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginHorizontal: -6,
    marginBottom: 8,
  },
  card: {
    width: '50%',
    paddingHorizontal: 6,
    marginBottom: 12,
  },
  surface: {
    backgroundColor: colors.white,
    borderRadius: 20,
    paddingVertical: 18,
    paddingHorizontal: 18,
  },
  topLine: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  iconWrap: {
    alignItems: 'center',
    backgroundColor: '#edf7ff',
    borderRadius: 12,
    height: 30,
    justifyContent: 'center',
    width: 30,
  },
  value: {
    fontSize: 28,
    fontWeight: '800',
    color: colors.ink,
  },
  label: {
    color: colors.slate,
    fontSize: 13,
  },
});
